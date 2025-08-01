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
use App\Service\DataProcessingService;
use App\Service\VariantService;
use App\Service\SearchService;
use App\Service\ProductService;


#[Route('/product')]
class ProductController extends AbstractController
{
    private const CSRF_TOKEN_ID = 'product_form';
    
    private const TYPE_MAPPING = [
        'colors' => ColorListing::class,
        'brands' => BrandListing::class,
        'marketplaces' => MarketplaceListing::class,
        'categories' => CategoryListing::class
    ];

    private CsrfTokenManagerInterface $csrfTokenManager;
    private SecurityValidationService $securityService;
    private DataProcessingService $dataProcessor;
    private VariantService $variantService;
    private SearchService $searchService;
    private ProductService $productService;
    private LoggerInterface $logger;


    public function __construct(
        CsrfTokenManagerInterface $csrfTokenManager,
        SecurityValidationService $securityService,
        DataProcessingService $dataProcessor,
        VariantService $variantService,
        SearchService $searchService,
        ProductService $productService
    ) {
        $this->csrfTokenManager = $csrfTokenManager;
        $this->securityService = $securityService;
        $this->dataProcessor = $dataProcessor;
        $this->variantService = $variantService;
        $this->searchService = $searchService;
        $this->productService = $productService;
    }
    
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
            $this->addFlash('danger', 'Sayfa Yüklenirken Bir Hata Oluştu: ' . $e->getMessage());
            return $this->redirectToRoute('product');
        }
    }

    #[Route('/create', name: 'product_create', methods: ['POST'])]
    public function create(Request $request): Response
    {
        $requestData = $this->parseRequestData($request);
        $result = $this->productService->processProduct($requestData);
        if (!$result['success']) {
            if (isset($result['errors'])) {
                return $this->handleValidationErrors($result['errors'], $request);
            } else {
                return $this->handleProductCreationError(
                    $result['exception'] ?? new \Exception($result['message'] ?? 'Bilinmeyen hata'), 
                    $request, 
                    $requestData['isUpdate'] ?? false
                );
            }
        }
        return $this->createSuccessResponse($request, $requestData['isUpdate'], $result['product']);
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
            if ($this->variantService->colorExists($colorName)) {
                return new JsonResponse(['success' => false, 'message' => 'Bu renk zaten mevcut.']);
            }
            $color = $this->variantService->createColor($colorName);
            return new JsonResponse(['success' => true, 'id' => $color->getId()]);
        } catch (\Exception $e) {
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
            return new JsonResponse(['success' => false, 'message' => 'Varyant silinirken hata oluştu']);
        }
    }

    // ===========================================
    // HELPERS
    // ===========================================

    private function handleSecurityError(string $message, Request $request): Response
    {
        if ($request->isXmlHttpRequest()) {
            return new JsonResponse(['success' => false, 'message' => $message], 403);
        }
        $this->addFlash('danger', $message);
        return $this->redirectToRoute('product');
    }

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

    // ===========================================
    // RESPONSE HANDLERS
    // ===========================================

    private function handleValidationErrors(array $errors, Request $request): Response
    {
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
        if ($request->isXmlHttpRequest()) {
            return new JsonResponse([
                'success' => false,
                'message' => $errorMessage
            ], 500);
        }
        $this->addFlash('danger', $errorMessage);
        return $this->redirectToRoute('product');
    }

}