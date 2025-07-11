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
            $categoryFilter = $request->query->get('category');
            $searchQuery = $request->query->get('search', '');
            $asinFilter = trim($request->query->get('asin', ''));
            $brandFilter = trim($request->query->get('brand', ''));
            $eanFilter = trim($request->query->get('ean', ''));
            $initialProducts = $this->searchService->getFilteredProducts(
                limit: self::DEFAULT_LIMIT,
                offset: 0,
                categoryFilter: $categoryFilter,
                searchQuery: $searchQuery,
                asinFilter: $asinFilter,
                brandFilter: $brandFilter,
                eanFilter: $eanFilter
            );
            return $this->render('catalog/catalog.html.twig', [
                'categories' => $categories,
                'initialProducts' => $initialProducts['products'],
                'totalProducts' => $initialProducts['total'],
                'hasMore' => $initialProducts['hasMore'],
                'currentCategory' => $categoryFilter,
                'currentSearch' => $searchQuery,
                'currentAsin' => $asinFilter,
                'currentBrand' => $brandFilter,
                'currentEan' => $eanFilter,
                'limit' => self::DEFAULT_LIMIT
            ]);
        } catch (\Exception $e) {
            $this->addFlash('danger', 'Katalog yüklenirken bir hata oluştu.');
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
            $categoryFilter = $request->query->get('category');
            $searchQuery = trim($request->query->get('search', ''));
            $asinFilter = trim($request->query->get('asin', ''));
            $brandFilter = trim($request->query->get('brand', ''));
            $eanFilter = trim($request->query->get('ean', ''));
            
            $result = $this->searchService->getFilteredProducts(
                $limit, 
                $offset, 
                $categoryFilter, 
                $searchQuery,
                $asinFilter,
                $brandFilter,
                $eanFilter
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

     #[Route('/export/excel', name: 'catalog_export_excel', methods: ['GET'])]
    public function exportToExcel(Request $request): StreamedResponse
    {
        try {
            $categoryFilter = $request->query->get('category');
            $searchQuery = trim($request->query->get('search', ''));
            $asinFilter = trim($request->query->get('asin', ''));
            $brandFilter = trim($request->query->get('brand', ''));
            $eanFilter = trim($request->query->get('ean', ''));
            
            return $this->exportService->exportFilteredProductsToCsv(
                self::EXPORT_MAX_PRODUCTS,
                0,
                $categoryFilter,
                $searchQuery,
                $asinFilter,
                $brandFilter,
                $eanFilter
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

}