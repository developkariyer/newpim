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

#[Route('/catalog')]
class CatalogController extends AbstractController
{
    // Constants
    private const DEFAULT_LIMIT = 20;
    private const MAX_LIMIT = 100;
    private const SEARCH_MIN_LENGTH = 2;
    private const EXPORT_MAX_PRODUCTS = 50000;
    
    // ===========================================
    // MAIN ROUTES
    // ===========================================

    #[Route('', name: 'catalog')]
    public function index(Request $request): Response
    {
        try {
            $categories = $this->getAvailableCategories();
            $categoryFilter = $request->query->get('category');
            $searchQuery = $request->query->get('search', '');
            $asinFilter = trim($request->query->get('asin', ''));
            $brandFilter = trim($request->query->get('brand', ''));
            $eanFilter = trim($request->query->get('ean', ''));
            $initialProducts = $this->getProducts(
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
            $asinFilter = trim($request->query->get('asin', ''));
            $brandFilter = trim($request->query->get('brand', ''));
            $eanFilter = trim($request->query->get('ean', ''));
            
            $result = $this->getProducts(
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
            $asinFilter = trim($request->query->get('asin', ''));
            $brandFilter = trim($request->query->get('brand', ''));
            $eanFilter = trim($request->query->get('ean', ''));
            $result = $this->getProducts(limit: self::EXPORT_MAX_PRODUCTS, offset: 0, categoryFilter: $categoryFilter, searchQuery: $searchQuery,asinFilter: $asinFilter, brandFilter: $brandFilter, eanFilter: $eanFilter);
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

    private function getProducts(int $limit, int $offset, ?string $categoryFilter = null, string $searchQuery = '',?string $asinFilter = null, ?string $brandFilter = null, ?string $eanFilter = null   ): array
    {
        try {
            $listing = new ProductListing();
            $conditions = ["published = 1", "type IS NULL OR type != 'variant'"];
            $params = [];
            $parentIdsFromAdvancedFilters = [];
            $hasAdvancedFilter = false;
            if (!empty($categoryFilter)) {
                $category = $this->getCategoryByKey($categoryFilter);
                if ($category) {
                    $conditions[] = "productCategory__id = ?";
                    $params[] = $category->getId();
                }
            }
            if (!empty($searchQuery) && strlen($searchQuery) >= self::SEARCH_MIN_LENGTH) {
                $searchCondition = "(name LIKE ? OR productIdentifier LIKE ? OR description LIKE ?)";
                $conditions[] = $searchCondition;
                $searchParam = "%" . $searchQuery . "%";
                $params[] = $searchParam;
                $params[] = $searchParam;
                $params[] = $searchParam;
            }

            if (!empty($asinFilter)) {
                $hasAdvancedFilter = true;
                $asinParentIds = $this->getParentProductIdsByVariantAsin($asinFilter);
                error_log("ASIN '$asinFilter' için bulunan parent ID'ler: " . json_encode($asinParentIds));
                $parentIdsFromAdvancedFilters[] = $asinParentIds;
            }

            if (!empty($brandFilter)) {
                $hasAdvancedFilter = true;
                $brandParentIds = $this->getParentProductIdsByVariantBrand($brandFilter);
                error_log("Brand '$brandFilter' için bulunan parent ID'ler: " . json_encode($brandParentIds));
                $parentIdsFromAdvancedFilters[] = $brandParentIds;
            }

            if (!empty($eanFilter)) {
                $hasAdvancedFilter = true;
                $eanParentIds = $this->getParentProductIdsByVariantEan($eanFilter);
                error_log("EAN '$eanFilter' için bulunan parent ID'ler: " . json_encode($eanParentIds));
                $parentIdsFromAdvancedFilters[] = $eanParentIds;
            }

            if ($hasAdvancedFilter) {
                if (count($parentIdsFromAdvancedFilters) === 1) {
                    $finalParentIds = $parentIdsFromAdvancedFilters[0];
                } else {
                    $finalParentIds = array_intersect(...$parentIdsFromAdvancedFilters);
                }
                
                error_log("Final parent ID'ler: " . json_encode($finalParentIds));
                
                if (empty($finalParentIds)) {
                    $conditions[] = "oo_id = -1";
                } else {
                    $placeholders = implode(',', array_fill(0, count($finalParentIds), '?'));
                    $conditions[] = "oo_id IN ($placeholders)";
                    $params = array_merge($params, array_values($finalParentIds));
                }
            }
            $listing->setCondition(implode(" AND ", $conditions), $params);
            $listing->setLimit($limit);
            $listing->setOffset($offset);
            $listing->setOrderKey('creationDate');
            $listing->setOrder('DESC');
            $products = $listing->load();
            $total = $listing->getTotalCount();
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
            $customTableData = $product->getCustomFieldTable();
            if (!is_array($customTableData)) {
                return [];
            }
            $customRows = [];
            $customTableTitle = '';
            foreach ($customTableData as $index => $row) {
                $deger = $row['deger'] ?? $row['value'] ?? '';
                if (!empty($deger) && $deger !== '[object Object]') {
                    if ($index === 0) {
                        $customTableTitle = $deger;
                    } 
                }
            }
            $variants = $this->getProductVariants($product, $customTableTitle);
            $category = $product->getProductCategory();
            $categoryInfo = $category ? [
                'id' => $category->getId(),
                'name' => $category->getKey(),
                'displayName' => $category->getCategory() ?: $category->getKey()
            ] : null;
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

    private function getProductVariants(Product $product, $customTableTitle): array
    {
        try {
            if (!$product->hasChildren()) {
                return [];
            }
            $variants = [];
            $productVariants = $product->getChildren([Product::OBJECT_TYPE_VARIANT], true);
            foreach ($productVariants as $variant) {
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
                    'customFieldTitle' => $customTableTitle,
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
                if (!$category->hasChildren()) {
                    $categories[] = [
                        'id' => $category->getId(),
                        'key' => $category->getKey(),
                        'name' => $category->getCategory() ?: $category->getKey(),
                        'productCount' => $this->getCategoryProductCount($category->getId())
                    ];
                }
            }
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

    private function getParentProductIdsByVariantAsin(string $asinValue): array
    {
        try {
            $variantListing = new ProductListing();
            $variantListing->setCondition("type = 'variant' AND published = 1");
            $variants = $variantListing->getObjects();
            $parentIds = [];
            foreach ($variants as $variant) {
                $asinObjects = $variant->getAsin();
                if ($asinObjects) {
                    if (is_array($asinObjects)) {
                        foreach ($asinObjects as $asinObj) {
                            if ($asinObj->getAsin() && stripos($asinObj->getAsin(), $asinValue) !== false) {
                                $parentIds[] = $variant->getParentId();
                                break;
                            }
                            if ($asinObj->getFnskus() && stripos($asinObj->getFnskus(), $asinValue) !== false) {
                                $parentIds[] = $variant->getParentId();
                                break;
                            }
                        }
                    } else {
                        if ($asinObjects->getAsin() && stripos($asinObjects->getAsin(), $asinValue) !== false) {
                            $parentIds[] = $variant->getParentId();
                        }
                        if ($asinObjects->getFnskus() && stripos($asinObjects->getFnskus(), $asinValue) !== false) {
                            $parentIds[] = $variant->getParentId();
                        }
                    }
                }
            }
            
            return array_unique(array_filter($parentIds));
        } catch (\Exception $e) {
            error_log('Get parent product IDs by variant ASIN error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Brand değeri bulunan varyantların ana ürün ID'lerini getirir
     */
    private function getParentProductIdsByVariantBrand(string $brandValue): array
    {
        try {
            $variantListing = new ProductListing();
            $variantListing->setCondition("type = 'variant' AND published = 1");
            $variants = $variantListing->getObjects();
            
            $parentIds = [];
            foreach ($variants as $variant) {
                $brandObjects = $variant->getBrandItems();
                if ($brandObjects) {
                    if (is_array($brandObjects)) {
                        foreach ($brandObjects as $brandObj) {
                            if ($brandObj->getKey() && stripos($brandObj->getKey(), $brandValue) !== false) {
                                $parentIds[] = $variant->getParentId();
                                break;
                            }
                        }
                    } else {
                        if ($brandObjects->getKey() && stripos($brandObjects->getKey(), $brandValue) !== false) {
                            $parentIds[] = $variant->getParentId();
                        }
                    }
                }
            }
            
            return array_unique(array_filter($parentIds));
        } catch (\Exception $e) {
            error_log('Get parent product IDs by variant Brand error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * EAN değeri bulunan varyantların ana ürün ID'lerini getirir
     */
    private function getParentProductIdsByVariantEan(string $eanValue): array
    {
        try {
            $variantListing = new ProductListing();
            $variantListing->setCondition("type = 'variant' AND published = 1");
            $variants = $variantListing->getObjects();
            
            $parentIds = [];
            foreach ($variants as $variant) {
                $eanObjects = $variant->getEans();
                if ($eanObjects && is_array($eanObjects)) {
                    foreach ($eanObjects as $eanObj) {
                        if ($eanObj->getGTIN() && stripos($eanObj->getGTIN(), $eanValue) !== false) {
                            $parentIds[] = $variant->getParentId();
                            break;
                        }
                    }
                }
            }
            
            return array_unique(array_filter($parentIds));
        } catch (\Exception $e) {
            error_log('Get parent product IDs by variant EAN error: ' . $e->getMessage());
            return [];
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
        echo "\xEF\xBB\xBF";
        $output = fopen('php://output', 'w');
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
                        $index === 0 ? $product['id'] : '', 
                        $index === 0 ? $product['name'] : '',
                        $index === 0 ? $product['productIdentifier'] : '',
                        $index === 0 ? $product['productCode'] : '',
                        $index === 0 ? ($product['category'] ? $product['category']['displayName'] : '') : '',
                        $index === 0 ? $product['description'] : '',
                        $index === 0 ? $product['variantCount'] : '',
                        $index === 0 ? ($product['createdAt'] ?? '') : '',
                        $index === 0 ? ($product['modifiedAt'] ?? '') : '',
                        $variant['id'],
                        $variant['name'],
                        $eansString,
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
        $input = str_replace(
            ['ı', 'ğ', 'ü', 'ş', 'ö', 'ç', 'İ', 'Ğ', 'Ü', 'Ş', 'Ö', 'Ç'],
            ['i', 'g', 'u', 's', 'o', 'c', 'I', 'G', 'U', 'S', 'O', 'C'],
            $input
        );
        $input = preg_replace('/[^a-zA-Z0-9_-]/', '_', $input);
        $input = preg_replace('/_+/', '_', $input);
        $input = trim($input, '_');
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