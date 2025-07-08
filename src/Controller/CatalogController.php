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

#[Route('/catalog')]
class CatalogController extends AbstractController
{
    // Constants
    private const DEFAULT_LIMIT = 20;
    private const MAX_LIMIT = 100;
    private const SEARCH_MIN_LENGTH = 2;
    
    // ===========================================
    // MAIN ROUTES
    // ===========================================

    #[Route('', name: 'catalog')]
    public function index(Request $request): Response
    {
        try {
            // Get initial data for page load
            $categories = $this->getAvailableCategories();
            $categoryFilter = $request->query->get('category');
            $searchQuery = $request->query->get('search', '');
            
            // Get initial products (first page)
            $initialProducts = $this->getProducts(
                limit: self::DEFAULT_LIMIT,
                offset: 0,
                categoryFilter: $categoryFilter,
                searchQuery: $searchQuery
            );

            return $this->render('catalog/catalog.html.twig', [
                'categories' => $categories,
                'initialProducts' => $initialProducts['products'],
                'totalProducts' => $initialProducts['total'],
                'hasMore' => $initialProducts['hasMore'],
                'currentCategory' => $categoryFilter,
                'currentSearch' => $searchQuery,
                'limit' => self::DEFAULT_LIMIT
            ]);

        } catch (\Exception $e) {
            error_log('Catalog page error: ' . $e->getMessage());
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

            $result = $this->getProducts($limit, $offset, $categoryFilter, $searchQuery);

            return new JsonResponse([
                'success' => true,
                'products' => $result['products'],
                'total' => $result['total'],
                'hasMore' => $result['hasMore'],
                'offset' => $offset,
                'limit' => $limit
            ]);

        } catch (\Exception $e) {
            error_log('Products API error: ' . $e->getMessage());
            
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

            $result = $this->getProducts($limit, 0, $categoryFilter, $searchQuery);

            return new JsonResponse([
                'success' => true,
                'products' => $result['products'],
                'total' => $result['total'],
                'hasMore' => $result['hasMore'],
                'query' => $searchQuery
            ]);

        } catch (\Exception $e) {
            error_log('Search API error: ' . $e->getMessage());
            
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
            
            // Get all products for export (no limit)
            $result = $this->getProducts(limit: 10000, offset: 0, categoryFilter: $categoryFilter, searchQuery: $searchQuery);
            $products = $result['products'];

            $response = new StreamedResponse();
            
            $response->setCallback(function() use ($products, $categoryFilter, $searchQuery) {
                $this->generateExcelOutput($products, $categoryFilter, $searchQuery);
            });

            $filename = $this->generateExcelFilename($categoryFilter, $searchQuery);
            
            $response->headers->set('Content-Type', 'application/vnd.ms-excel; charset=utf-8');
            $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
            $response->headers->set('Cache-Control', 'max-age=0');

            return $response;

        } catch (\Exception $e) {
            error_log('Excel export error: ' . $e->getMessage());
            $this->addFlash('danger', 'Excel dosyası oluşturulurken hata oluştu.');
            
            return $this->redirectToRoute('catalog');
        }
    }

    // ===========================================
    // DATA RETRIEVAL METHODS
    // ===========================================

    private function getProducts(int $limit, int $offset, ?string $categoryFilter = null, string $searchQuery = ''): array
    {
        try {
            $listing = new ProductListing();
            
            // Base condition - only main products (not variants)
            $conditions = ["published = 1", "type IS NULL OR type != 'variant'"];
            $params = [];

            // Category filter
            if (!empty($categoryFilter)) {
                $category = $this->getCategoryByKey($categoryFilter);
                if ($category) {
                    $conditions[] = "productCategory__id = ?";
                    $params[] = $category->getId();
                }
            }

            // Search filter
            if (!empty($searchQuery) && strlen($searchQuery) >= self::SEARCH_MIN_LENGTH) {
                $searchCondition = "(name LIKE ? OR productIdentifier LIKE ? OR description LIKE ?)";
                $conditions[] = $searchCondition;
                $searchParam = "%" . $searchQuery . "%";
                $params[] = $searchParam;
                $params[] = $searchParam;
                $params[] = $searchParam;
            }

            // Set conditions
            $listing->setCondition(implode(" AND ", $conditions), $params);
            
            // Set pagination
            $listing->setLimit($limit);
            $listing->setOffset($offset);
            
            // Set ordering
            $listing->setOrderKey('creationDate');
            $listing->setOrder('DESC');

            // Load products
            $products = $listing->load();
            $total = $listing->getTotalCount();

            // Format products
            $formattedProducts = [];
            foreach ($products as $product) {
                $formattedProducts[] = $this->formatProductForCatalog($product);
            }

            return [
                'products' => $formattedProducts,
                'total' => $total,
                'hasMore' => ($offset + $limit) < $total,
                'currentOffset' => $offset,
                'currentLimit' => $limit
            ];

        } catch (\Exception $e) {
            error_log('Get products error: ' . $e->getMessage());
            
            return [
                'products' => [],
                'total' => 0,
                'hasMore' => false,
                'currentOffset' => 0,
                'currentLimit' => $limit
            ];
        }
    }

    private function formatProductForCatalog(Product $product): array
    {
        try {
            // Get variants
            $variants = $this->getProductVariants($product);
            
            // Get category info
            $category = $product->getProductCategory();
            $categoryInfo = $category ? [
                'id' => $category->getId(),
                'name' => $category->getKey(),
                'displayName' => $category->getCategory() ?: $category->getKey()
            ] : null;

            // Get main product image
            $image = $product->getImage();
            $imagePath = $image ? $image->getFullPath() : null;

            return [
                'id' => $product->getId(),
                'name' => $product->getName(),
                'productIdentifier' => $product->getProductIdentifier(),
                'description' => $product->getDescription(),
                'productCode' => $product->getProductCode(),
                'category' => $categoryInfo,
                'imagePath' => $imagePath,
                'imageUrl' => $imagePath ? '/var/assets' . $imagePath : null,
                'variants' => $variants,
                'variantCount' => count($variants),
                'hasVariants' => !empty($variants),
            ];

        } catch (\Exception $e) {
            error_log('Format product error: ' . $e->getMessage());
            
            return [
                'id' => $product->getId(),
                'name' => $product->getName() ?: 'Unknown Product',
                'productIdentifier' => $product->getProductIdentifier() ?: 'Unknown',
                'description' => '',
                'productCode' => '',
                'category' => null,
                'imagePath' => null,
                'imageUrl' => null,
                'variants' => [],
                'variantCount' => 0,
                'hasVariants' => false,
                'createdAt' => null,
                'modifiedAt' => null
            ];
        }
    }

    private function getProductVariants(Product $product): array
    {
        try {
            if (!$product->hasChildren()) {
                return [];
            }
            $variants = [];
            $productVariants = $product->getChildren([Product::OBJECT_TYPE_VARIANT]);

            foreach ($productVariants as $variant) {
                if (!$variant->getPublished()) {
                    continue; // Skip unpublished variants
                }

                // Get color info
                $colorObject = $variant->getVariationColor();
                $colorInfo = $colorObject ? [
                    'id' => $colorObject->getId(),
                    'name' => $colorObject->getColor()
                ] : null;

                $eansObjects = $variant->getEans() ?? [];
                $eans = [];
                if (is_array($eansObjects)) {
                    foreach ($eansObjects as $eanObject) {
                        if ($eanObject->getGTIN()) {
                            $eans[] = $eanObject->getGTIN();
                        }
                    }
                }
                $variants[] = [
                    'id' => $variant->getId(),
                    'name' => $variant->getName(),
                    'eans' => $eans ?? [],
                    'iwasku' => $variant->getIwasku(),
                    'productCode' => $variant->getProductCode(),
                    'variationSize' => $variant->getVariationSize(),
                    'color' => $colorInfo,
                    'customField' => $variant->getCustomField(),
                    'published' => $variant->getPublished(),
                ];
            }

            return $variants;

        } catch (\Exception $e) {
            error_log('Get product variants error: ' . $e->getMessage());
            return [];
        }
    }

    private function getAvailableCategories(): array
    {
        try {
            $listing = new CategoryListing();
            $listing->setCondition("published = 1");
            $listing->setOrderKey('key');
            $listing->setOrder('ASC');
            $listing->load();

            $categories = [];
            foreach ($listing as $category) {
                // Only include leaf categories (categories without children that can have products)
                if (!$category->hasChildren()) {
                    $categories[] = [
                        'id' => $category->getId(),
                        'key' => $category->getKey(),
                        'name' => $category->getCategory() ?: $category->getKey(),
                        'productCount' => $this->getCategoryProductCount($category->getId())
                    ];
                }
            }

            // Sort by product count (categories with more products first)
            usort($categories, function($a, $b) {
                return $b['productCount'] - $a['productCount'];
            });

            return $categories;

        } catch (\Exception $e) {
            error_log('Get available categories error: ' . $e->getMessage());
            return [];
        }
    }

    private function getCategoryByKey(string $categoryKey): ?Category
    {
        try {
            $listing = new CategoryListing();
            $listing->setCondition("published = 1 AND `key` = ?", [$categoryKey]);
            $listing->setLimit(1);
            
            return $listing->current();

        } catch (\Exception $e) {
            error_log('Get category by key error: ' . $e->getMessage());
            return null;
        }
    }

    private function getCategoryProductCount(int $categoryId): int
    {
        try {
            $listing = new ProductListing();
            $listing->setCondition("published = 1 AND productCategory__id = ? AND (type IS NULL OR type != 'variant')", [$categoryId]);
            
            return $listing->getTotalCount();

        } catch (\Exception $e) {
            error_log('Get category product count error: ' . $e->getMessage());
            return 0;
        }
    }

    // ===========================================
    // EXCEL EXPORT METHODS
    // ===========================================

    private function generateExcelOutput(array $products, ?string $categoryFilter, string $searchQuery): void
    {
        // Set UTF-8 BOM for proper Turkish character support
        echo "\xEF\xBB\xBF";
        
        // Open output stream
        $output = fopen('php://output', 'w');

        // Write headers
        $headers = [
            'Ürün ID',
            'Ürün Adı',
            'Ürün Tanıtıcı',
            'Ürün Kodu',
            'Kategori',
            'Açıklama',
            'Toplam Varyant',
            'Oluşturma Tarihi',
            'Güncelleme Tarihi',
            'Varyant ID',
            'Varyant Adı',
            'EAN Kodları', 
            'IWASKU',
            'Varyant Kodu',
            'Beden',
            'Renk',
            'Custom Alan',
            'Varyant Durumu',
            'Varyant Oluşturma'
        ];

        fputcsv($output, $headers, ';');

        foreach ($products as $product) {
            if (empty($product['variants'])) {
                $row = [
                    $product['id'],
                    $product['name'],
                    $product['productIdentifier'],
                    $product['productCode'],
                    $product['category'] ? $product['category']['displayName'] : '',
                    $product['description'],
                    0,
                    $product['createdAt'] ?? '',
                    $product['modifiedAt'] ?? '',
                    // Empty variant columns
                    '', '', '', '', '', '', '', '', '', ''
                ];
                fputcsv($output, $row, ';');
            } else {
                foreach ($product['variants'] as $index => $variant) {
                    $eansString = '';
                    if (isset($variant['eans']) && is_array($variant['eans']) && !empty($variant['eans'])) {
                        $eansString = implode(', ', $variant['eans']);
                    }

                    $row = [
                        $index === 0 ? $product['id'] : '', // Only show product info on first row
                        $index === 0 ? $product['name'] : '',
                        $index === 0 ? $product['productIdentifier'] : '',
                        $index === 0 ? $product['productCode'] : '',
                        $index === 0 ? ($product['category'] ? $product['category']['displayName'] : '') : '',
                        $index === 0 ? $product['description'] : '',
                        $index === 0 ? $product['variantCount'] : '',
                        $index === 0 ? ($product['createdAt'] ?? '') : '',
                        $index === 0 ? ($product['modifiedAt'] ?? '') : '',
                        // Variant columns
                        $variant['id'],
                        $variant['name'],
                        $eansString, // Array'den string'e çevrilmiş EAN'lar
                        $variant['iwasku'] ?: '',
                        $variant['productCode'] ?: '',
                        $variant['variationSize'] ?: '',
                        $variant['color'] ? $variant['color']['name'] : '',
                        $variant['customField'] ?: '',
                        $variant['published'] ? 'Aktif' : 'Pasif',
                        $variant['createdAt'] ?? ''
                    ];
                    fputcsv($output, $row, ';');
                }
            }
        }

        fclose($output);
    }

    private function generateExcelFilename(?string $categoryFilter, string $searchQuery): string
    {
        $timestamp = date('Y-m-d_H-i-s');
        $filename = 'urun_katalogu_' . $timestamp;

        if (!empty($categoryFilter)) {
            $filename .= '_kategori_' . $this->sanitizeFilename($categoryFilter);
        }

        if (!empty($searchQuery)) {
            $filename .= '_arama_' . $this->sanitizeFilename($searchQuery);
        }

        return $filename . '.csv';
    }

    private function sanitizeFilename(string $input): string
    {
        // Convert Turkish characters
        $input = str_replace(
            ['ı', 'ğ', 'ü', 'ş', 'ö', 'ç', 'İ', 'Ğ', 'Ü', 'Ş', 'Ö', 'Ç'],
            ['i', 'g', 'u', 's', 'o', 'c', 'I', 'G', 'U', 'S', 'O', 'C'],
            $input
        );
        
        // Remove special characters and spaces
        $input = preg_replace('/[^a-zA-Z0-9_-]/', '_', $input);
        
        // Remove multiple underscores
        $input = preg_replace('/_+/', '_', $input);
        
        // Trim underscores
        $input = trim($input, '_');
        
        // Limit length
        return substr($input, 0, 50);
    }

    // ===========================================
    // UTILITY METHODS
    // ===========================================

    private function validatePaginationParams(Request $request): array
    {
        $limit = min(max((int)$request->query->get('limit', self::DEFAULT_LIMIT), 1), self::MAX_LIMIT);
        $offset = max((int)$request->query->get('offset', 0), 0);
        
        return ['limit' => $limit, 'offset' => $offset];
    }

    private function logRequest(Request $request, string $action): void
    {
        error_log("=== CATALOG {$action} REQUEST ===");
        error_log('Method: ' . $request->getMethod());
        error_log('URI: ' . $request->getRequestUri());
        error_log('Query params: ' . json_encode($request->query->all()));
        error_log('User Agent: ' . $request->headers->get('User-Agent'));
    }
}