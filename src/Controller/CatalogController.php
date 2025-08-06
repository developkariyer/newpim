<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;
use Pimcore\Model\DataObject\Product;
use Pimcore\Model\DataObject\Category;
use Pimcore\Model\DataObject\Product\Listing as ProductListing;
use Pimcore\Model\DataObject\Category\Listing as CategoryListing;
use Pimcore\Model\DataObject\Brand\Listing as BrandListing;
use Pimcore\Model\DataObject\Asin\Listing as AsinListing;
use Pimcore\Model\DataObject\Ean\Listing as EanListing;
use App\Service\SearchService;
use App\Service\ExportService;
use Psr\Log\LoggerInterface;
use Pimcore\Db;

#[Route('/catalog')]
class CatalogController extends AbstractController
{
    // Constants
    private const DEFAULT_LIMIT = 20;
    private const MAX_LIMIT = 100;
    private const SEARCH_MIN_LENGTH = 2;
    private const EXPORT_MAX_PRODUCTS = 50000;

    private SearchService $searchService;
    private ExportService $exportService;
    private LoggerInterface $logger;

    public function __construct(SearchService $searchService, ExportService $exportService, LoggerInterface $logger)
    {
        $this->exportService = $exportService;
        $this->searchService = $searchService;
        $this->logger = $logger;
    }
    
    // ===========================================
    // MAIN ROUTES
    // ===========================================

    #[Route('', name: 'catalog')]
    public function index(Request $request): Response
    {
        try {
            $categories = $this->searchService->getAvailableCategories();
            $filters = $this->extractFiltersFromRequest($request);
            $initialProducts = $this->searchService->getFilteredProducts(
                ...$filters,
                limit: self::DEFAULT_LIMIT,
                offset: 0,
            );
            return $this->render('catalog/catalog.html.twig', [
                'categories' => $categories,
                'initialProducts' => $initialProducts['products'],
                'totalProducts' => $initialProducts['total'],
                'hasMore' => $initialProducts['hasMore'],
                'currentCategory' => $filters['categoryFilter'],
                'currentSearch' => $filters['searchQuery'],
                'currentIwasku' => $filters['iwaskuFilter'],
                'currentAsin' => $filters['asinFilter'],
                'currentBrand' => $filters['brandFilter'],
                'currentEan' => $filters['eanFilter'],
                'limit' => self::DEFAULT_LIMIT
            ]);
        } catch (\Exception $e) {
            $this->addFlash('danger', 'Katalog Yüklenirken Bir Hata Oluştu.');
            return $this->render('catalog/catalog.html.twig', [
                'categories' => [],
                'initialProducts' => [],
                'totalProducts' => 0,
                'hasMore' => false,
                'currentCategory' => null,
                'currentSearch' => '',
                'limit' => self::DEFAULT_LIMIT
            ]);
        }
    }

    #[Route('/api/products', name: 'catalog_api_products', methods: ['GET'])]
    public function getProductsApi(Request $request): JsonResponse
    {
        try {
            $limit = min((int)$request->query->get('limit', self::DEFAULT_LIMIT), self::MAX_LIMIT);
            $offset = max((int)$request->query->get('offset', 0), 0);
            $filters = $this->extractFiltersFromRequest($request);
            $result = $this->searchService->getFilteredProducts(
                ... $filters,
                limit: $limit, 
                offset: $offset
            );
            return new JsonResponse([
                'success' => true,
                'products' => $result['products'],
                'total' => $result['total'],
                'hasMore' => $result['hasMore'],
                'offset' => $offset,
                'limit' => $limit
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Ürünler yüklenirken hata oluştu.',
                'products' => [],
                'total' => 0,
                'hasMore' => false
            ], 500);
        }
    }

    #[Route('/api/search', name: 'catalog_api_search', methods: ['GET'])]
    public function searchProducts(Request $request): JsonResponse
    {
        try {
            $searchQuery = trim($request->query->get('q', ''));
            $categoryFilter = $request->query->get('category');
            $limit = min((int)$request->query->get('limit', self::DEFAULT_LIMIT), self::MAX_LIMIT);
            if (strlen($searchQuery) < self::SEARCH_MIN_LENGTH) {
                return new JsonResponse([
                    'success' => true,
                    'products' => [],
                    'total' => 0,
                    'hasMore' => false,
                    'message' => 'En az ' . self::SEARCH_MIN_LENGTH . ' karakter girin.'
                ]);
            }
            $result = $this->searchService->getFilteredProducts($limit, 0, $categoryFilter, $searchQuery);
            return new JsonResponse([
                'success' => true,
                'products' => $result['products'],
                'total' => $result['total'],
                'hasMore' => $result['hasMore'],
                'query' => $searchQuery
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Arama sırasında hata oluştu.',
                'products' => [],
                'total' => 0,
                'hasMore' => false
            ], 500);
        }
    }

    #[Route('/api/marketplace-listings/{sku}', name: 'catalog_api_marketplace_listings', methods: ['GET'])]
    public function getMarketplaceListings(string $sku): JsonResponse
    {
        try {
            $db = Db::get();
            $sql = "SELECT 
                        marketplace_key, 
                        marketplace_sku, 
                        marketplace_price, 
                        marketplace_currency, 
                        marketplace_stock, 
                        status, 
                        marketplace_product_url,
                        last_updated
                    FROM iwa_marketplaces_catalog 
                    WHERE marketplace_sku = ? 
                    ORDER BY marketplace_key ASC";
            $listings = $db->fetchAllAssociative($sql, [$sku]);
            return new JsonResponse([
                'success' => true,
                'sku' => $sku,
                'listings' => $listings,
                'total' => count($listings)
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Marketplace listings error', [
                'sku' => $sku,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return new JsonResponse([
                'success' => false,
                'message' => 'Pazaryeri bilgileri yüklenirken hata oluştu.',
                'listings' => [],
                'total' => 0
            ], 500);
        }
    }

    #[Route('/export/excel', name: 'catalog_export_excel', methods: ['GET'])]
    public function exportToExcel(Request $request): StreamedResponse
    {
        try {
            $filters = $this->extractFiltersFromRequest($request);
            return $this->exportService->exportFilteredProductsToCsv(
                ...$filters,
                limit: self::EXPORT_MAX_PRODUCTS,
                offset: 0
            );
        } catch (\Exception $e) {
            $this->logger->error('Excel export error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->addFlash('danger', 'Excel dosyası oluşturulurken hata oluştu.');
            return $this->redirectToRoute('catalog');
        }
    }

    private function extractFiltersFromRequest(Request $request): array
    {
        return [
            'categoryFilter' => $request->query->get('category'),
            'searchQuery'    => trim($request->query->get('search', $request->query->get('q', ''))), 
            'iwaskuFilter'   => trim($request->query->get('iwasku', '')),
            'asinFilter'     => trim($request->query->get('asin', '')),
            'brandFilter'    => trim($request->query->get('brand', '')),
            'eanFilter'      => trim($request->query->get('ean', '')),
        ];
    }

}