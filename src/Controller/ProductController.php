<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
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

#[Route('/product')]
class ProductController extends AbstractController
{
    private const PRODUCTS_MAIN_FOLDER_ID = 1246;
    private const COLORS_FOLDER_ID = 1247;
    private const MAX_IMAGE_SIZE = 10 * 1024 * 1024; // 10MB
    private const ALLOWED_IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
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

    // ===========================================
    // MAIN ROUTES
    // ===========================================

    #[Route('', name: 'product')]
    public function index(): Response
    {
        try {
            $editProductId = $request->query->get('edit');
            $selectedProductData = null;
            if ($editProductId) {
                $selectedProduct = Product::getById((int)$editProductId);
                if ($selectedProduct) {
                    $selectedProductData = $this->buildProductData($selectedProduct);
                    error_log('Loading product for edit: ' . $selectedProduct->getId());
                } else {
                    $this->addFlash('warning', 'Düzenlenecek ürün bulunamadı.');
                }
            }
            return $this->render('product/product.html.twig', [
                'categories' => $this->getGenericListing(self::TYPE_MAPPING['categories'], "published = 1", fn($category) => $category->getCategory()),
                'colors' => $this->getGenericListing(self::TYPE_MAPPING['colors']),
                'brands' => $this->getGenericListing(self::TYPE_MAPPING['brands']),
                'marketplaces' => $this->getGenericListing(self::TYPE_MAPPING['marketplaces']),
                'selectedProduct' => $selectedProductData
            ]);
        } catch (\Exception $e) {
            $this->addFlash('danger', 'Sayfa yüklenirken bir hata oluştu: ' . $e->getMessage());
            return $this->redirectToRoute('product', [], Response::HTTP_SEE_OTHER);
        }
    }

    #[Route('/create', name: 'product_create', methods: ['POST'])]
    public function create(Request $request): Response
    {
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
                $this->createProductVariants($product, $requestData['variations']);
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
            $searchCondition = $this->buildSearchCondition($query);
            $results = $this->getGenericListing(self::TYPE_MAPPING[$type], $searchCondition);
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
            $product = $this->findProductByQuery($query);
            if (!$product) {
                return new JsonResponse(['items' => []]);
            }
            $productData = $this->buildProductData($product);
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
            $color = $this->createColor($colorName);
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
            $variant = $this->findVariantByData($data['productId'], $data['variantData']);
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
            $imageAsset = $this->uploadProductImage($data['imageFile'], $data['productIdentifier'] ?: $data['productName']);
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
            $imageAsset = $this->uploadProductImage($data['imageFile'], $product->getProductIdentifier());
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
            $customTable = $this->processCustomTableData($data['customTableData']);
            if ($customTable) {
                $product->setCustomFieldTable($customTable);
            }
        }
        if (!$data['isUpdate']) {
            $this->generateProductCode($product);
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
    // VARIANT MANAGEMENT
    // ===========================================

    private function createProductVariants(Product $parentProduct, array $variations): void
    {
        foreach ($variations as $variantData) {
            $this->createSingleVariant($parentProduct, $variantData);
        }
    }

    private function createSingleVariant(Product $parentProduct, array $variantData): void
    {
        $variant = new Product();
        $variant->setParent($parentProduct);
        $variant->setType(Product::OBJECT_TYPE_VARIANT);
        $variantKey = $this->generateVariantKey($variantData);
        $fullKey = $parentProduct->getProductIdentifier() . '-' . $parentProduct->getName() . '-' . $variantKey;
        $variant->setKey($fullKey);
        $variant->setName($fullKey);
        $this->setVariantProperties($variant, $variantData);
        $this->generateVariantCodes($variant, $parentProduct->getProductIdentifier());
        $variant->setPublished(true);
        $variant->save();
        error_log('Variant created: ' . $variant->getId());
    }

    private function generateVariantKey(array $variantData): string
    {
        $parts = array_filter([
            $variantData['renk'] ?? '',
            $variantData['beden'] ?? '',
            $variantData['custom'] ?? ''
        ]);
        return implode('-', $parts) ?: 'variant';
    }

    private function setVariantProperties(Product $variant, array $variantData): void
    {
        if (!empty($variantData['renk'])) {
            $color = $this->findColorByName($variantData['renk']);
            if ($color) {
                $variant->setVariationColor($color);
            }
        }
        if (!empty($variantData['beden'])) {
            $variant->setVariationSize($variantData['beden']);
        }
        if (!empty($variantData['custom'])) {
            $variant->setCustomField($variantData['custom']);
        }
    }

    private function findVariantByData(int $productId, array $variantData): ?Product
    {
        $product = Product::getById($productId);
        if (!$product) {
            return null;
        }
        $variants = $product->getChildren([Product::OBJECT_TYPE_VARIANT]);
        foreach ($variants as $variant) {
            if ($this->variantMatches($variant, $variantData)) {
                return $variant;
            }
        }
        return null;
    }

    private function variantMatches(Product $variant, array $variantData): bool
    {
        $variantColor = $variant->getVariationColor() ? $variant->getVariationColor()->getColor() : null;
        $variantSize = $variant->getVariationSize() ?: null;
        $variantCustom = $variant->getCustomField() ?: null;
        return $variantColor === ($variantData['color'] ?? null) &&
               $variantSize === ($variantData['size'] ?? null) &&
               $variantCustom === ($variantData['custom'] ?? null);
    }

    // ===========================================
    // IMAGE HANDLING
    // ===========================================

    private function uploadProductImage($imageFile, string $productKey): ?\Pimcore\Model\Asset\Image
    {
        try {
            // Validate image file
            if (!$this->validateImageFile($imageFile)) {
                return null;
            }

            // Get or create asset folder
            $assetFolder = $this->getOrCreateAssetFolder();
            
            // Generate filename
            $filename = $this->generateImageFilename($imageFile, $productKey);
            
            // Read file content
            $fileContent = $this->readImageFileContent($imageFile);
            if (!$fileContent) {
                return null;
            }

            // Create asset
            $imageAsset = new \Pimcore\Model\Asset\Image();
            $imageAsset->setFilename($filename);
            $imageAsset->setParent($assetFolder);
            $imageAsset->setMimeType($imageFile->getMimeType());
            $imageAsset->setData($fileContent);
            $imageAsset->save();

            error_log('Image uploaded successfully: ' . $imageAsset->getId());
            return $imageAsset;

        } catch (\Exception $e) {
            error_log('Image upload error: ' . $e->getMessage());
            return null;
        }
    }

    private function validateImageFile($imageFile): bool
    {
        if (!$imageFile || !$imageFile->isValid()) {
            error_log('Invalid image file');
            return false;
        }

        if ($imageFile->getSize() > self::MAX_IMAGE_SIZE) {
            error_log('Image file too large: ' . $imageFile->getSize());
            return false;
        }

        if (!in_array($imageFile->getMimeType(), self::ALLOWED_IMAGE_TYPES)) {
            error_log('Invalid image type: ' . $imageFile->getMimeType());
            return false;
        }

        return true;
    }

    private function readImageFileContent($imageFile): ?string
    {
        $tempPath = $imageFile->getPathname();
        
        if (!file_exists($tempPath) || !is_readable($tempPath)) {
            error_log('Image file not readable: ' . $tempPath);
            return null;
        }

        $content = file_get_contents($tempPath);
        
        if ($content === false || strlen($content) === 0) {
            error_log('Cannot read image content');
            return null;
        }

        return $content;
    }

    private function getOrCreateAssetFolder(): \Pimcore\Model\Asset\Folder
    {
        $assetFolder = \Pimcore\Model\Asset::getByPath('/products');
        
        if (!$assetFolder) {
            $assetFolder = new \Pimcore\Model\Asset\Folder();
            $assetFolder->setFilename('products');
            $assetFolder->setParent(\Pimcore\Model\Asset::getByPath('/'));
            $assetFolder->save();
        }

        return $assetFolder;
    }

    private function generateImageFilename($imageFile, string $productKey): string
    {
        $extension = strtolower(pathinfo($imageFile->getClientOriginalName(), PATHINFO_EXTENSION)) ?: 'jpg';
        $safeFilename = $this->generateSafeFilename($productKey);
        return $safeFilename . '_' . time() . '_' . uniqid() . '.' . $extension;
    }

    // ===========================================
    // DATA PROCESSING
    // ===========================================

    private function processCustomTableData(string $customTableData): ?array
    {
        $decoded = json_decode($customTableData, true);
        
        if (!is_array($decoded) || !isset($decoded['rows']) || !is_array($decoded['rows'])) {
            return null;
        }

        $customFieldTable = [];
        $title = trim($decoded['title'] ?? '');
        
        if ($title !== '') {
            $customFieldTable[] = ['deger' => $title];
        }

        foreach ($decoded['rows'] as $row) {
            if (isset($row['deger']) && !empty($row['deger'])) {
                $customFieldTable[] = ['deger' => $row['deger']];
            }
        }

        return empty($customFieldTable) ? null : $customFieldTable;
    }

    private function buildProductData(Product $product): array
    {
        $variants = $this->getProductVariants($product);
        $variantAnalysis = $this->analyzeVariants($variants);
        return [
            'id' => $product->getId(),
            'name' => $product->getName(),
            'productIdentifier' => $product->getProductIdentifier(),
            'description' => $product->getDescription(),
            'categoryId' => $product->getProductCategory()?->getId(),
            'categoryName' => $product->getProductCategory()?->getKey(),
            'brands' => $this->formatObjectCollection($product->getBrandItems(), Brand::class),
            'marketplaces' => $this->formatObjectCollection($product->getMarketplaces(), Marketplace::class),
            'imagePath' => $product->getImage()?->getFullPath(),
            'hasVariants' => !empty($variants),
            'variants' => $variants,
            'variantColors' => array_values(array_unique($variantAnalysis['colors'], SORT_REGULAR)),
            'sizeTable' => $this->formatSizeTable($product, $variantAnalysis['usedSizes']),
            'customTable' => $this->formatCustomTable($product, $variantAnalysis['usedCustoms']),
            'usedSizes' => array_unique($variantAnalysis['usedSizes']),
            'usedCustoms' => array_values(array_unique($variantAnalysis['usedCustoms'])),
            'usedColorIds' => array_unique($variantAnalysis['usedColorIds']),
            'canEditSizeTable' => true,
            'canEditColors' => true,
            'canEditCustomTable' => true,
            'canCreateVariants' => true
        ];
    }

    // ===========================================
    // CODE GENERATION
    // ===========================================

    private function generateProductCode(Product $product): void
    {
        $productCode = $this->generateUniqueCode(self::UNIQUE_CODE_LENGTH);
        $product->setProductCode($productCode);
    }

    private function generateVariantCodes(Product $variant, string $parentIdentifier): void
    {
        if ($variant->getType() === Product::OBJECT_TYPE_VARIANT) {
            $iwasku = str_pad(str_replace('-', '', $parentIdentifier), 7, '0', STR_PAD_RIGHT);
            $productCode = $this->generateUniqueCode(self::UNIQUE_CODE_LENGTH);
            $variant->setProductCode($productCode);
            $variant->setIwasku($iwasku . $productCode);
        }
    }

    private function generateUniqueCode(int $length = 5): string
    {
        $maxAttempts = 1000;
        $attempts = 0;
        while ($attempts < $maxAttempts) {
            $candidateCode = $this->generateRandomString($length);
            if (!$this->codeExists($candidateCode)) {
                return $candidateCode;
            }
            $attempts++;
        }
        throw new \Exception('Unable to generate unique code after ' . $maxAttempts . ' attempts');
    }

    private function generateRandomString(int $length = 5): string
    {
        $characters = 'ABCDEFGHJKMNPQRSTVWXYZ1234567890';
        $result = '';
        for ($i = 0; $i < $length; $i++) {
            $result .= $characters[mt_rand(0, strlen($characters) - 1)];
        }
        return $result;
    }

    private function codeExists(string $code): bool
    {
        $listing = new ProductListing();
        $listing->setCondition('productCode = ?', [$code]);
        $listing->setUnpublished(true);
        $listing->setLimit(1);
        return $listing->count() > 0;
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
        $object = $this->getObjectById(self::CLASS_MAPPING[$type], $intId);
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
        $objects = [];
        foreach ($ids as $id) {
            if (!empty($id)) {
                $intId = (int)(is_array($id) ? $id[0] : $id);
                $object = $this->getObjectById(self::CLASS_MAPPING[$type], $intId);       
                if (!$object) {
                    $errors[] = "{$displayName} ID {$intId} bulunamadı";
                } else {
                    $objects[] = $object;
                }
            }
        }
        return $objects;
    }

    private function getObjectById(string $className, int $id): ?object
    {
        if (!class_exists($className)) {
            return null;
        }
        try {
            return $className::getById($id);
        } catch (\Exception $e) {
            return null;
        }
    }

    // ===========================================
    // SEARCH AND LISTING
    // ===========================================

    private function buildSearchCondition(string $query): string
    {
        if (empty($query)) {
            return "published = 1";
        }
        $escapedQuery = addslashes($query);
        return "published = 1 AND LOWER(`key`) LIKE LOWER('%{$escapedQuery}%')";
    }

    private function findProductByQuery(string $query): ?Product
    {
        $listing = new ProductListing();
        $listing->setCondition('productIdentifier LIKE ? OR name LIKE ?', ["%$query%", "%$query%"]);
        $listing->setLimit(1);
        $products = $listing->load();
        return $products[0] ?? null;
    }

    private function getGenericListing(string $listingClass, string $condition = "published = 1", ?callable $nameGetter = null): array
    {
        $listing = new $listingClass();
        $listing->setCondition($condition);
        $listing->load();
        
        $results = [];
        foreach ($listing as $item) {
            $results[] = [
                'id' => $item->getId(),
                'name' => $nameGetter ? $nameGetter($item) : $item->getKey(),
            ];
        }
        
        return $results;
    }

    // ===========================================
    // COLOR MANAGEMENT
    // ===========================================

    private function colorExists(string $colorName): bool
    {
        $listing = new ColorListing();
        $listing->setCondition('color = ?', [$colorName]);
        return $listing->count() > 0;
    }

    private function createColor(string $colorName): Color
    {
        $color = new Color();
        $color->setKey($colorName);
        $color->setParentId(self::COLORS_FOLDER_ID);
        $color->setColor($colorName);
        $color->setPublished(true);
        $color->save();
        return $color;
    }

    private function findColorByName(string $colorName): ?Color
    {
        $listing = new ColorListing();
        $listing->setCondition('color = ?', [$colorName]);
        $listing->setLimit(1);
        return $listing->current();
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

    // ===========================================
    // FORMATTING HELPERS
    // ===========================================

    private function getProductVariants(Product $product): array
    {
        if (!$product->hasChildren()) {
            return [];
        }
        $variants = [];
        $productVariants = $product->getChildren([Product::OBJECT_TYPE_VARIANT]);
        foreach ($productVariants as $variant) {
            $variants[] = [
                'id' => $variant->getId(),
                'name' => $variant->getName(),
                'color' => $variant->getVariationColor()?->getColor(),
                'colorId' => $variant->getVariationColor()?->getId(),
                'size' => $variant->getVariationSize(),
                'custom' => $variant->getCustomField(),
            ];
        }
        return $variants;
    }

    private function analyzeVariants(array $variants): array
    {
        $colors = [];
        $usedSizes = [];
        $usedCustoms = [];
        $usedColorIds = [];
        foreach ($variants as $variant) {
            if ($variant['colorId']) {
                $usedColorIds[] = $variant['colorId'];
                $colors[] = [
                    'id' => $variant['colorId'],
                    'name' => $variant['color']
                ];
            }
            if ($variant['size']) {
                $usedSizes[] = $variant['size'];
            }
            if ($variant['custom']) {
                $usedCustoms[] = $variant['custom'];
            }
        }
        return [
            'colors' => $colors,
            'usedSizes' => $usedSizes,
            'usedCustoms' => $usedCustoms,
            'usedColorIds' => $usedColorIds
        ];
    }

    private function formatObjectCollection($items, string $expectedClass): array
    {
        if (!$items) {
            return [];
        }
        $result = [];
        $itemsArray = is_array($items) ? $items : [$items];
        foreach ($itemsArray as $item) {
            if ($item instanceof $expectedClass) {
                $result[] = [
                    'id' => $item->getId(),
                    'name' => $item->getKey()
                ];
            }
        }
        return $result;
    }

    private function formatSizeTable(Product $product, array $usedSizes): array
    {
        $sizeTableData = $product->getVariationSizeTable();
        if (!is_array($sizeTableData)) {
            return [];
        }
        $sizeTable = [];
        foreach ($sizeTableData as $row) {
            $beden = $row['beden'] ?? $row['label'] ?? '';
            $sizeTable[] = [
                'beden' => $beden,
                'en' => $row['en'] ?? $row['width'] ?? '',
                'boy' => $row['boy'] ?? $row['length'] ?? '',
                'yukseklik' => $row['yukseklik'] ?? $row['height'] ?? '',
                'locked' => in_array($beden, $usedSizes)
            ];
        }
        return $sizeTable;
    }

    private function formatCustomTable(Product $product, array $usedCustoms): array
    {
        $customTableData = $product->getCustomFieldTable();
        if (!is_array($customTableData)) {
            return [];
        }
        $customRows = [];
        $customTitle = '';
        foreach ($customTableData as $index => $row) {
            $deger = $row['deger'] ?? $row['value'] ?? '';
            if (!empty($deger) && $deger !== '[object Object]') {
                if ($index === 0) {
                    $customTitle = $deger;
                } else {
                    $customRows[] = [
                        'deger' => $deger,
                        'locked' => in_array($deger, $usedCustoms)
                    ];
                }
            }
        }
        return [
            'title' => $customTitle,
            'rows' => $customRows
        ];
    }
}