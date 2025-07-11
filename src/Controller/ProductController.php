<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Pimcore\Model\DataObject\Product;
use Pimcore\Model\DataObject\Brand;
use Pimcore\Model\DataObject\Category;
use Pimcore\Model\DataObject\Marketplace;
use Pimcore\Model\DataObject\Color;
use Pimcore\Model\DataObject\Category\Listing as CategoryListing;
use Pimcore\Model\DataObject\Color\Listing as ColorListing;
use Pimcore\Model\DataObject\Brand\Listing as BrandListing;
use Pimcore\Model\DataObject\Marketplace\Listing as MarketplaceListing;
use Pimcore\Model\DataObject\Product\Listing as ProductListing;
use App\Service\SecurityValidationService;
use App\Service\FileSecurityService;
use App\Service\AssetManagementService;
use App\Service\DataProcessingService;
use App\Service\CodeGenerationService;
use App\Service\VariantService;
use App\Service\SearchService;


#[Route('/product')]
class ProductController extends AbstractController
{
    private CsrfTokenManagerInterface $csrfTokenManager;
    private SecurityValidationService $securityService;
    private FileSecurityService $fileService;
    private AssetManagementService $assetService;
    private DataProcessingService $dataProcessor;
    private CodeGenerationService $codeGenerator;
    private VariantService $variantService;
    private SearchService $searchService;

    public function __construct(
        CsrfTokenManagerInterface $csrfTokenManager,
        SecurityValidationService $securityService,
        FileSecurityService $fileService,
        AssetManagementService $assetService,
        DataProcessingService $dataProcessor,
        CodeGenerationService $codeGenerator,
        VariantService $variantService,
        SearchService $searchService
    ) {
        $this->csrfTokenManager = $csrfTokenManager;
        $this->securityService = $securityService;
        $this->fileService = $fileService;
        $this->assetService = $assetService;
        $this->dataProcessor = $dataProcessor;
        $this->codeGenerator = $codeGenerator;
        $this->variantService = $variantService;
        $this->searchService = $searchService;
    }

    // Constants for configuration
    private const PRODUCTS_MAIN_FOLDER_ID = 1246;
    private const COLORS_FOLDER_ID = 1247;
    private const MAX_IMAGE_SIZE = 10 * 1024 * 1024; // 10MB
    private const ALLOWED_IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    private const ALLOWED_IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    private const IMAGE_MAGIC_NUMBERS = [
        'jpg' => ["\xFF\xD8\xFF"],
        'jpeg' => ["\xFF\xD8\xFF"],
        'png' => ["\x89\x50\x4E\x47\x0D\x0A\x1A\x0A"],
        'gif' => ["\x47\x49\x46\x38\x37\x61", "\x47\x49\x46\x38\x39\x61"],
        'webp' => ["\x52\x49\x46\x46"]
    ];
    private const MAX_FILENAME_LENGTH = 255;
    private const SUSPICIOUS_PATTERNS = [
        '/<\?php/i',
        '/<script/i',
        '/javascript:/i',
        '/vbscript:/i',
        '/onload=/i',
        '/onerror=/i'
    ];
    private const UNIQUE_CODE_LENGTH = 5;

    private const TYPE_MAPPING = [
        'colors' => ColorListing::class,
        'brands' => BrandListing::class,
        'marketplaces' => MarketplaceListing::class,
        'categories' => CategoryListing::class
    ];

    private const CLASS_MAPPING = [
        'color' => Color::class,
        'brand' => Brand::class,
        'marketplace' => Marketplace::class,
        'category' => Category::class
    ];
    private const CSRF_TOKEN_ID = 'product_form';
    private const MAX_REQUEST_SIZE = 50 * 1024 * 1024;
    private const ALLOWED_ORIGINS = []; 
    private const XSS_DANGEROUS_TAGS = [
        '<script', '</script>', '<iframe', '</iframe>', '<object', '</object>',
        '<embed', '</embed>', '<form', '</form>', 'javascript:', 'vbscript:',
        'onload=', 'onerror=', 'onclick=', 'onmouseover=', 'onfocus='
    ];

    // ===========================================
    // MAIN ROUTES
    // ===========================================

    #[Route('', name: 'product')]
    public function index(Request $request): Response
    {
        try {
            $editProductId = $request->query->get('edit');
            $selectedProductData = null;
            if ($editProductId) {
                $selectedProduct = Product::getById((int)$editProductId);
                if ($selectedProduct) {
                    $selectedProductData = $this->dataProcessor->buildProductData($selectedProduct);
                    error_log('Loading product for edit: ' . $selectedProduct->getId());
                } else {
                    $this->addFlash('warning', 'Düzenlenecek ürün bulunamadı.');
                }
            }
            $csrfToken = $this->csrfTokenManager->getToken(self::CSRF_TOKEN_ID)->getValue();
            return $this->render('product/product.html.twig', [
                'categories' => $this->searchService->getGenericListing(self::TYPE_MAPPING['categories'], "published = 1", fn($category) => $category->getCategory()),
                'colors' => $this->searchService->getGenericListing(self::TYPE_MAPPING['colors']),
                'brands' => $this->searchService->getGenericListing(self::TYPE_MAPPING['brands']),
                'marketplaces' => $this->searchService->getGenericListing(self::TYPE_MAPPING['marketplaces']),
                'selectedProduct' => $selectedProductData,
                'csrf_token' => $csrfToken
            ]);
        } catch (\Exception $e) {
            error_log('Product page error: ' . $e->getMessage());
            $this->addFlash('danger', 'Sayfa yüklenirken bir hata oluştu: ' . $e->getMessage());
            return $this->redirectToRoute('product');
        }
    }

    #[Route('/create', name: 'product_create', methods: ['POST'])]
    public function create(Request $request): Response
    {
        if (!$this->securityService->validateCsrfToken($request)) {
            return $this->handleSecurityError('CSRF token geçersiz', $request);
        }
        $this->logRequest($request);
        try {
            $requestData = $this->parseRequestData($request);
            $validationResult = $this->validateProductData($requestData);
            if (!$validationResult['isValid']) {
                return $this->handleValidationErrors($validationResult['errors'], $request);
            }
            $product = $requestData['isUpdate'] 
                ? $this->updateExistingProduct($requestData)
                : $this->createNewProduct($requestData);

            $this->setProductData($product, $requestData, $validationResult['objects']);
            $product->setPublished(true);
            $product->save();
            error_log('Product saved successfully: ' . $product->getId());
            if (!empty($requestData['variations'])) {
                $this->variantService->createProductVariants($product, $requestData['variations']);
            }
            return $this->createSuccessResponse($request, $requestData['isUpdate'], $product);
        } catch (\Throwable $e) {
            return $this->handleProductCreationError($e, $request, $requestData['isUpdate'] ?? false);
        }
    }

    #[Route('/search/{type}', name: 'product_search', methods: ['GET'])]
    public function searchByType(Request $request, string $type): JsonResponse
    {
        try {
            $query = trim($request->query->get('q', ''));
            if (!isset(self::TYPE_MAPPING[$type])) {
                return new JsonResponse(['error' => 'Invalid search type'], 400);
            }
            $searchCondition = $this->searchService->buildSearchCondition($query);
            $results = $this->searchService->getGenericListing(self::TYPE_MAPPING[$type], $searchCondition);
            return new JsonResponse(['items' => $results]);
        } catch (\Exception $e) {
            error_log('Search error: ' . $e->getMessage());
            return new JsonResponse(['error' => 'Search failed'], 500);
        }
    }

    #[Route('/search-products', name: 'product_search_products', methods: ['GET'])]
    public function searchProducts(Request $request): JsonResponse
    {
        try {
            $query = trim($request->query->get('q', ''));
            if (strlen($query) < 2) {
                return new JsonResponse(['items' => []]);
            }
            $product = $this->searchService->findProductByQuery($query);
            if (!$product) {
                return new JsonResponse(['items' => []]);
            }
            $productData = $this->dataProcessor->buildProductData($product);
            return new JsonResponse(['items' => [$productData]]);

        } catch (\Exception $e) {
            error_log('Product search error: ' . $e->getMessage());
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/add-color', name: 'product_add_color', methods: ['POST'])]
    public function addColor(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            $colorName = trim($data['name'] ?? '');
            if (!$colorName) {
                return new JsonResponse(['success' => false, 'message' => 'Renk adı boş olamaz.']);
            }
            if ($this->colorExists($colorName)) {
                return new JsonResponse(['success' => false, 'message' => 'Bu renk zaten mevcut.']);
            }
            $color = $this->variantService->createColor($colorName);
            return new JsonResponse(['success' => true, 'id' => $color->getId()]);
        } catch (\Exception $e) {
            error_log('Add color error: ' . $e->getMessage());
            return new JsonResponse(['success' => false, 'message' => 'Renk eklenirken hata oluştu.']);
        }
    }

    #[Route('/delete-variant', name: 'product_delete_variant', methods: ['POST'])]
    public function deleteVariant(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            $variant = $this->variantService->findVariantByData($data['productId'], $data['variantData']);
            if (!$variant) {
                return new JsonResponse(['success' => false, 'message' => 'Varyant bulunamadı']);
            }
            $variant->setPublished(false);
            $variant->save();
            return new JsonResponse(['success' => true, 'message' => 'Varyant silindi']);
        } catch (\Exception $e) {
            error_log('Delete variant error: ' . $e->getMessage());
            return new JsonResponse(['success' => false, 'message' => 'Varyant silinirken hata oluştu']);
        }
    }

    // ===========================================
    // PRODUCT CREATION HELPERS
    // ===========================================

    private function parseRequestData(Request $request): array
    {
        $editingProductId = $request->get('editingProductId');
        $variations = $request->get('variationsData');
        return [
            'isUpdate' => !empty($editingProductId),
            'editingProductId' => $editingProductId,
            'productName' => $request->get('productName'),
            'productIdentifier' => $request->get('productIdentifier'),
            'productDescription' => $request->get('productDescription'),
            'imageFile' => $request->files->get('productImage'),
            'categoryId' => $request->get('productCategory'),
            'brandIds' => $request->get('brands', []),
            'marketplaceIds' => $request->get('marketplaces', []),
            'colorIds' => $request->get('colors', []),
            'sizeTableData' => $request->get('sizeTableData'),
            'customTableData' => $request->get('customTableData'),
            'variations' => $variations ? json_decode($variations, true) : null
        ];
    }

    private function validateProductData(array $data): array
    {
        $errors = [];
        $objects = [];
        $objects['category'] = $this->validateSingleObject('category', $data['categoryId'], $errors, 'Kategori');
        $objects['brands'] = $this->validateMultipleObjects('brand', $data['brandIds'], $errors, 'Marka');
        $objects['marketplaces'] = $this->validateMultipleObjects('marketplace', $data['marketplaceIds'], $errors, 'Pazaryeri');
        $objects['colors'] = $this->validateMultipleObjects('color', $data['colorIds'], $errors, 'Renk');
        if (!$data['isUpdate'] && (!$data['imageFile'] || !$data['imageFile']->isValid())) {
            $errors[] = 'Geçerli bir resim dosyası gerekli.';
        }
        return [
            'isValid' => empty($errors),
            'errors' => $errors,
            'objects' => $objects
        ];
    }

    private function createNewProduct(array $data): Product
    {
        $imageAsset = null;
        if ($data['imageFile'] && $data['imageFile']->isValid()) {
            $imageAsset = $this->assetService->uploadProductImage($data['imageFile'], $data['productIdentifier'] ?: $data['productName']);
        }
        $parentFolder = $this->createProductFolderStructure($data['productIdentifier'], $data['categoryId']);
        $product = new Product();
        $product->setParent($parentFolder);
        $product->setKey($data['productIdentifier'] . ' ' . $data['productName']);
        $product->setProductIdentifier($data['productIdentifier']);
        
        if ($imageAsset) {
            $product->setImage($imageAsset);
        }
        error_log('Creating new product in folder: ' . $parentFolder->getFullPath());
        return $product;
    }

    private function updateExistingProduct(array $data): Product
    {
        $product = Product::getById($data['editingProductId']);
        if (!$product) {
            throw new \Exception('Güncellenecek ürün bulunamadı.');
        }
        if ($data['imageFile'] && $data['imageFile']->isValid()) {
            $imageAsset = $this->assetService->uploadProductImage($data['imageFile'], $product->getProductIdentifier());
            if ($imageAsset) {
                $product->setImage($imageAsset);
            }
        }
        error_log('Updating existing product: ' . $product->getId());
        return $product;
    }

    private function setProductData(Product $product, array $data, array $objects): void
    {
        $product->setName($data['productName']);
        $product->setDescription($data['productDescription']);
        $product->setProductCategory($objects['category']);
        $product->setBrandItems($objects['brands']);
        $product->setMarketplaces($objects['marketplaces']);
        if ($data['sizeTableData']) {
            $sizeTable = json_decode($data['sizeTableData'], true);
            if (is_array($sizeTable)) {
                $product->setVariationSizeTable($sizeTable);
            }
        }
        if ($data['customTableData']) {
            $customTable = $this->dataProcessor->processCustomTableData($data['customTableData']);
            if ($customTable) {
                $product->setCustomFieldTable($customTable);
            }
        }
        if (!$data['isUpdate']) {
            $this->codeGenerator->generateProductCode($product);
        }
    }

    private function createProductFolderStructure(string $productIdentifier, int $categoryId): \Pimcore\Model\DataObject\Folder
    {
        $productsFolder = \Pimcore\Model\DataObject\Folder::getById(self::PRODUCTS_MAIN_FOLDER_ID);
        if (!$productsFolder) {
            throw new \Exception('Products main folder not found');
        }
        $category = Category::getById($categoryId);
        if (!$category) {
            throw new \Exception('Category not found');
        }
        $categoryFolder = $this->getOrCreateFolder($productsFolder, $category->getKey());
        $identifierPrefix = strtoupper(explode('-', $productIdentifier)[0]);
        $identifierFolder = $this->getOrCreateFolder($categoryFolder, $identifierPrefix);
        return $identifierFolder;
    }

    private function getOrCreateFolder(\Pimcore\Model\DataObject\Folder $parent, string $folderName): \Pimcore\Model\DataObject\Folder
    {
        $folderPath = $parent->getFullPath() . '/' . $folderName;
        $folder = \Pimcore\Model\DataObject\Folder::getByPath($folderPath);
        if (!$folder) {
            $folder = new \Pimcore\Model\DataObject\Folder();
            $folder->setKey($folderName);
            $folder->setParent($parent);
            $folder->save();
            error_log('Created folder: ' . $folder->getFullPath());
        }
        return $folder;
    }


    // ===========================================
    // VALIDATION HELPERS
    // ===========================================

    private function validateSingleObject(string $type, $id, array &$errors, string $displayName): ?object
    {
        if (empty($id)) {
            return null;
        }
        $intId = (int)(is_array($id) ? $id[0] : $id);
        $object = $this->searchService->getObjectById(self::CLASS_MAPPING[$type], $intId);
        if (!$object) {
            $errors[] = "{$displayName} ID {$intId} bulunamadı";
        }
        return $object;
    }

    private function validateMultipleObjects(string $type, array $ids, array &$errors, string $displayName): array
    {
        if (empty($ids)) {
            return [];
        }
        $cleanIds = array_map('intval', array_filter($ids, 'is_numeric'));
        if (empty($cleanIds)) {
            return [];
        }
        $listingClass = self::TYPE_MAPPING[$type . 's'] ?? null;
        if (!$listingClass || !class_exists($listingClass)) {
            $errors[] = "{$displayName} için geçersiz tip: {$type}";
            return [];
        }
        $listing = new $listingClass();
        $listing->setCondition('oo_id IN (?)', [array_unique($cleanIds)]);
        $listing->setUnpublished(true);
        $objects = $listing->load();
        if (count($objects) !== count(array_unique($cleanIds))) {
            $foundIds = [];
            foreach ($objects as $object) {
                $foundIds[] = $object->getId();
            }
            $missingIds = array_diff(array_unique($cleanIds), $foundIds);
            foreach ($missingIds as $missingId) {
                $errors[] = "{$displayName} ID {$missingId} bulunamadı";
            }
        }
        return $objects;
    }

    // ===========================================
    // RESPONSE HANDLERS
    // ===========================================

    private function handleValidationErrors(array $errors, Request $request): Response
    {
        error_log('Validation errors: ' . json_encode($errors));
        
        if ($request->isXmlHttpRequest()) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Validation hatası: ' . implode(', ', $errors),
                'errors' => $errors
            ], 400);
        }

        foreach ($errors as $error) {
            $this->addFlash('danger', $error);
        }
        
        return $this->redirectToRoute('product');
    }

    private function createSuccessResponse(Request $request, bool $isUpdate, Product $product): Response
    {
        $message = $isUpdate ? 'Ürün başarıyla güncellendi' : 'Ürün başarıyla oluşturuldu';
        if ($request->isXmlHttpRequest()) {
            return new JsonResponse([
                'success' => true,
                'message' => $message,
                'productId' => $product->getId()
            ]);
        }
        $this->addFlash('success', $message);
        return $this->redirectToRoute('product');
    }

    private function handleProductCreationError(\Throwable $e, Request $request, bool $isUpdate): Response
    {
        $errorMessage = ($isUpdate ? 'Ürün güncellenirken' : 'Ürün oluşturulurken') . ' bir hata oluştu: ' . $e->getMessage();
        
        error_log('Product creation error: ' . $e->getMessage());
        error_log('Stack trace: ' . $e->getTraceAsString());
        
        if ($request->isXmlHttpRequest()) {
            return new JsonResponse([
                'success' => false,
                'message' => $errorMessage
            ], 500);
        }
        
        $this->addFlash('danger', $errorMessage);
        return $this->redirectToRoute('product');
    }

    // ===========================================
    // UTILITIES
    // ===========================================

    private function logRequest(Request $request): void
    {
        error_log('=== PRODUCT CREATE REQUEST ===');
        error_log('Method: ' . $request->getMethod());
        error_log('Content-Type: ' . $request->headers->get('Content-Type'));
        error_log('Request data: ' . json_encode($request->request->all()));
        error_log('Files: ' . json_encode(array_keys($request->files->all())));
    }

    private function generateSafeFilename(string $input): string
    {
        $filename = mb_strtolower($input);
        $filename = str_replace(['ı', 'ğ', 'ü', 'ş', 'ö', 'ç'], ['i', 'g', 'u', 's', 'o', 'c'], $filename);
        $filename = preg_replace('/[^a-z0-9]/', '_', $filename);
        $filename = preg_replace('/_+/', '_', $filename);
        $filename = trim($filename, '_');
        
        return $filename ?: 'product';
    }

}