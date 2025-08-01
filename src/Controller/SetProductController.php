<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Pimcore\Model\DataObject\Product;
use Pimcore\Model\DataObject\Product\Listing as ProductListing;
use App\Service\SearchService;
use App\Service\ProductService;
use Psr\Log\LoggerInterface;

#[Route('/set-product')]
class SetProductController extends AbstractController
{
    private const CSRF_TOKEN_ID = 'set_product_form';

    private CsrfTokenManagerInterface $csrfTokenManager;
    private SearchService $searchService;
    private ProductService $productService;
    private LoggerInterface $logger;

    public function __construct(
        CsrfTokenManagerInterface $csrfTokenManager,
        SearchService $searchService,
        ProductService $productService,
        LoggerInterface $logger
    ) {
        $this->csrfTokenManager = $csrfTokenManager;
        $this->searchService = $searchService;
        $this->productService = $productService;
        $this->logger = $logger;
    }

    #[Route('', name: 'set-product')]
    public function index(Request $request): Response
    {
        try {
            $csrfToken = $this->csrfTokenManager->getToken(self::CSRF_TOKEN_ID)->getValue();
            return $this->render('setProduct/setProduct.html.twig', [
                'csrf_token' => $csrfToken
            ]);
        } catch (\Exception $e) {
            $this->addFlash('danger', 'Sayfa yüklenirken bir hata oluştu: ' . $e->getMessage());
            return $this->redirectToRoute('set-product');
        }
    }

    #[Route('/search-products', name: 'set_product_search_products', methods: ['GET'])]
    public function searchProducts(Request $request): JsonResponse
    {
        try {
            $query = trim($request->query->get('q', ''));
            if (strlen($query) < 2) {
                return new JsonResponse(['items' => []]);
            }
            $listing = new ProductListing();
            $listing->setCondition("iwasku LIKE ?", 
                ['%' . $query . '%']);
            $listing->setLimit(11);
            $results = [];
            foreach ($listing->getObjects() as $product) {
                $bundleProducts = [];
                $existBundleProducts = $product->getBundleProducts();
                if ($existBundleProducts && is_array($existBundleProducts) && !empty($existBundleProducts)) {
                    foreach ($existBundleProducts as $bundleItem) {
                        $bundleProducts[] = [
                            'id' => $bundleItem->getId(),
                            'name' => $bundleItem->getKey() ?? '',
                            'iwasku' => $bundleItem->getIwasku() ?? '',
                            'identifier' => $bundleItem->getProductIdentifier() ?? '',
                            'quantity' => 1
                        ];
                    }
                }
                $results[] = [
                    'id' => $product->getId(),
                    'name' => $product->getKey() ?? '',
                    'identifier' => $product->getProductIdentifier() ?? '',
                    'iwasku' => $product->getIwasku() ?? '',
                    'description' => $product->getDescription() ?? '',
                    'bundleProducts' => $bundleProducts,
                    'isSetProduct' => !empty($bundleProducts)
                ];
            }
            return new JsonResponse(['items' => $results]);
        } catch (\Exception $e) {
            $this->logger->error('Set product search error', [
                'query' => $query ?? '',
                'error' => $e->getMessage()
            ]);
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/create', name: 'set_product_create', methods: ['POST'])]
    public function create(Request $request): Response
    {
        try {
            $token = $request->get('_token');
            if (!$this->isCsrfTokenValid(self::CSRF_TOKEN_ID, $token)) {
                throw new \InvalidArgumentException('Geçersiz CSRF token');
            }
            $productId = $request->get('selectedProductId');
            $iwaskuItemsJson = $request->get('iwaskuItems', '[]');
            if (!$productId) {
                throw new \InvalidArgumentException('Ürün seçilmedi');
            }
            $iwaskuItems = json_decode($iwaskuItemsJson, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \InvalidArgumentException('İwasku ürünleri JSON formatı hatalı: ' . json_last_error_msg());
            }
            
            if (empty($iwaskuItems)) {
                throw new \InvalidArgumentException('İwasku ürünleri seçilmedi');
            }
            $product = Product::getById((int)$productId);
            if (!$product) {
                throw new \InvalidArgumentException('Seçilen ürün bulunamadı');
            }
            $this->createSetProduct($product, $iwaskuItems);
            $this->addFlash('success', 'Set ürün başarıyla oluşturuldu');
            return $this->redirectToRoute('set-product');

        } catch (\Exception $e) {
            $this->addFlash('danger', 'Set ürün oluşturulurken hata: ' . $e->getMessage());
            return $this->redirectToRoute('set-product');
        }
    }

    private function createSetProduct(Product $product, array $iwaskuItems): void
    {
        $this->logger->info('Set product created', [
            'productId' => $product->getId(),
            'iwaskuCount' => count($iwaskuItems)
        ]);
        $bundleProducts = [];
        foreach ($iwaskuItems as $item) {
            $bundleProduct = Product::getById((int)$item['id']);
            if ($bundleProduct) {
                $bundleProducts[] = $bundleProduct;
            } 
        }
        $product->setBundleProducts($bundleProducts);
        try {
            $product->save();
        } catch (\Exception $e) {
            $this->logger->error('Set product save error', [
                'productId' => $product->getId(),
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException('Set ürün kaydedilirken hata: ' . $e->getMessage());
        }
    }
}