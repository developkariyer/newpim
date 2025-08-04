<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Pimcore\Model\DataObject\Product;
use Pimcore\Model\DataObject\Product\Listing as ProductListing;
use Pimcore\Model\DataObject\Category;
use Pimcore\Model\DataObject\Category\Listing as CategoryListing;
use Pimcore\Model\DataObject\Asin\Listing as AsinListing;
use Pimcore\Model\DataObject\Brand\Listing as BrandListing;
use Pimcore\Model\DataObject\Ean\Listing as EanListing;



class SearchService
{
    private const MIN_SEARCH_LENGTH = 2;

    public function __construct(
        private LoggerInterface $logger,
        private DataProcessingService $dataProcessor,
        private DatabaseService $databaseService
    
    ) {}

    public function buildSearchCondition(string $query, bool $includePublishedCheck = true): string
    {
        if (empty($query)) {
            return $includePublishedCheck ? "published = 1" : "";
        }    
        $escapedQuery = addslashes($query);
        $condition = "LOWER(`key`) LIKE LOWER('%{$escapedQuery}%')";
        if ($includePublishedCheck) {
            $condition = "published = 1 AND $condition";
        }
        return $condition;
    }

    public function findProductByQuery(string $query, int $limit = 1): ?Product
    {
        if (empty($query)) {
            return null;
        }
        $listing = new ProductListing();
        $listing->setCondition('productIdentifier LIKE ? OR name LIKE ?', ["%$query%", "%$query%"]);
        $listing->setLimit($limit);
        $products = $listing->load();
        $result = $products[0] ?? null;
        return $result;
    }

    public function getGenericListing(string $listingClass, string $condition = "published = 1", ?callable $nameGetter = null): array
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

    public function getFilteredProducts(
        int $limit, 
        int $offset, 
        ?string $categoryFilter = null, 
        string $searchQuery = '',
        ?string $iwaskuFilter = null, 
        ?string $asinFilter = null, 
        ?string $brandFilter = null, 
        ?string $eanFilter = null
    ): array
    {
        try {
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
            if (!empty($searchQuery) && strlen($searchQuery) >= self::MIN_SEARCH_LENGTH) { 
                $searchCondition = "(name LIKE ? OR productIdentifier LIKE ? OR description LIKE ?)";
                $conditions[] = $searchCondition;
                $searchParam = "%" . $searchQuery . "%";
                $params[] = $searchParam;
                $params[] = $searchParam;
                $params[] = $searchParam;
            }
            if (!empty($iwaskuFilter)) {
                $hasAdvancedFilter = true;
                $iwaskuParentIds = $this->getParentProductIdsByVariantIwasku($iwaskuFilter);
                $parentIdsFromAdvancedFilters[] = $iwaskuParentIds;
            }
            if (!empty($asinFilter)) {
                $hasAdvancedFilter = true;
                $asinParentIds = $this->getParentProductIdsByVariantAsin($asinFilter);
                $parentIdsFromAdvancedFilters[] = $asinParentIds;
            }
            if (!empty($brandFilter)) {
                $hasAdvancedFilter = true;
                $brandParentIds = $this->getParentProductIdsByVariantBrand($brandFilter);
                $parentIdsFromAdvancedFilters[] = $brandParentIds;
            }
            if (!empty($eanFilter)) {
                $hasAdvancedFilter = true;
                $eanParentIds = $this->getParentProductIdsByVariantEan($eanFilter);
                $parentIdsFromAdvancedFilters[] = $eanParentIds;
            }
            if ($hasAdvancedFilter) {
                if (count($parentIdsFromAdvancedFilters) === 1) {
                    $finalParentIds = $parentIdsFromAdvancedFilters[0];
                } else {
                    $finalParentIds = array_intersect(...$parentIdsFromAdvancedFilters);
                }
                if (empty($finalParentIds)) {
                    $conditions[] = "oo_id = -1";  
                } else {
                    $placeholders = implode(',', array_fill(0, count($finalParentIds), '?'));
                    $conditions[] = "oo_id IN ($placeholders)";
                    $params = array_merge($params, array_values($finalParentIds));
                }
            }
            $listing = new ProductListing();
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
                'hasVariants' => !empty($variants)
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

    public function getAvailableCategories(): array
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

    public function getCategoryByKey(string $key): ?Category
    {
        $listing = new CategoryListing();
        $listing->setCondition("published = 1 AND `key` = ?", [$key]);
        $listing->setLimit(1);
        $categories = $listing->load();
        return $categories[0] ?? null;
    }

    private function getParentProductIdsByVariantIwasku(string $iwaskuValue): array
    {
        try {
            $variantListing = new ProductListing();
            $variantListing->setCondition(
                "type = 'variant' AND published = 1 AND iwasku LIKE ?",
                ["%" . $iwaskuValue . "%"]
            );
            $variants = $variantListing->getObjects();
            $parentIds = [];
            foreach ($variants as $variant) {
                $parentIds[] = $variant->getParentId();
            }
            return array_unique(array_filter($parentIds));
        } catch (\Exception $e) {
            error_log('Get parent product IDs by variant iwasku error: ' . $e->getMessage());
            return [];
        }
    }

    private function getParentProductIdsByVariantAsin(string $asinValue)
    {
        $this->logger->info('Searching for parent product IDs by variant ASIN: ' . $asinValue);
        $asinListing = new AsinListing();
        $asinListing->setCondition("LOWER(asin) LIKE LOWER(?) OR LOWER(fnskus) LIKE LOWER(?)", ["%$asinValue%", "%$asinValue%"]);
        $asinListing->setLimit(1);
        $asin = $asinListing->load();
        $asinObject = $asin[0] ?? null;
        if (!$asinObject) {
            return [];
        }
        $asinId = $asinObject->getId();
        $sql = "SELECT oo_id 
                FROM object_query_product 
                WHERE FIND_IN_SET(:asin, asin);";
        $result = $this->databaseService->fetchAllSql($sql, ['asin' => (string)$asinId]);
        $this->logger->info('SQL Result: ' . json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $variantIds = [];
        foreach ($result as $row) {
            $variantIds[] = (int)$row['oo_id'];
        }
        $this->logger->info('Found parent product IDs: ' . json_encode($variantIds, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $parentIds = [];
        foreach ($variantIds as $variantId) {
            $variant = Product::getById($variantId);
            if ($variant && $variant->getType() === 'variant') {
                $parentId = $variant->getParentId();
                if ($parentId) {
                    $this->logger->info('Found parent ID: ' . $parentId);
                    $parentIds[] = $parentId;
                }
            }
        }
        return array_unique(array_filter($parentIds));
    }

    private function getParentProductIdsByVariantBrand(string $brandValue): array
    {
        $this->logger->info('Searching for parent product IDs by variant Brand: ' . $brandValue);
        $brandListing = new BrandListing();
        $brandListing->setCondition("LOWER(name) LIKE LOWER(?)", ["%$brandValue%"]);
        $brandListing->setLimit(1);
        $brand = $brandListing->load();
        $brandObject = $brand[0] ?? null;
        if (!$brandObject) {
            return [];
        }
        $brandId = $brandObject->getId();
        $sql = "SELECT oo_id 
                FROM object_query_product 
                WHERE FIND_IN_SET(:brandItems, brandItems);";
        $result = $this->databaseService->fetchAllSql($sql, ['brandItems' => (string)$brandId]);
        $this->logger->info('SQL Result: ' . json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $variantIds = [];
        foreach ($result as $row) {
            $variantIds[] = (int)$row['oo_id'];
        }
        $this->logger->info('Found parent product IDs: ' . json_encode($variantIds, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $parentIds = [];
        foreach ($variantIds as $variantId) {
            $variant = Product::getById($variantId);
            if ($variant && $variant->getType() === 'variant') {
                $parentId = $variant->getParentId();
                if ($parentId) {
                    $this->logger->info('Found parent ID: ' . $parentId);
                    $parentIds[] = $parentId;
                }
            }
        }
        return array_unique(array_filter($parentIds));
    }
   
    private function getParentProductIdsByVariantEan(string $eanValue): array
    {
        $sql = "SELECT product__id FROM object_query_ean WHERE GTIN = :eanValue";
        $result  = $this->databaseService->fetchAllSql($sql, ['eanValue' => $eanValue]);
        $variantIds = [];
        foreach ($result as $row) {
            $variantIds[] = (int)$row['product__id'];
        }
        if (empty($variantIds)) {
            return [];
        }
        $parentIds = [];
        foreach ($variantIds as $variantId) {
            $variant = Product::getById($variantId);
            if ($variant && $variant->getType() === 'variant') {
                $parentId = $variant->getParentId();
                if ($parentId) {
                    $this->logger->info('Found parent ID: ' . $parentId);
                    $parentIds[] = $parentId;
                }
            }
        }
        return array_unique(array_filter($parentIds));
    }

    public function getProductVariants(Product $product, $customTableTitle): array
    {
        try {
            if (!$product->hasChildren()) {
                return [];
            }
            $variants = [];
            $productVariants = $product->getChildren([Product::OBJECT_TYPE_VARIANT], true);
            //$variantBundleProducts = $this->getVariantBundleProducts($variant);
            //$bundleProductCount = count($variantBundleProducts);
            //$hasBundleProducts = $bundleProductCount > 0;
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
                $asinObjects = $variant->getAsin() ?? [];
                if (is_array($asinObjects)) {
                    $formattedAsins = [];
                    foreach ($asinObjects as $asinObject) {
                        $asinData = [
                            'asin' => $asinObject->getAsin() ?: null,
                            'fnskus' => []
                        ];
                        $fnskusStr = $asinObject->getFnskus();
                        if ($fnskusStr) {
                            if (is_string($fnskusStr)) {
                                $fnskuArray = array_filter(array_map('trim', explode(',', $fnskusStr)));
                                $asinData['fnskus'] = $fnskuArray;
                            }
                            elseif (is_array($fnskusStr)) {
                                $asinData['fnskus'] = array_filter($fnskusStr);
                            }
                        }
                        if ($asinData['asin']) {
                            $formattedAsins[] = $asinData;
                        }
                    }
                }
                $variants[] = [
                    'id' => $variant->getId(),
                    'name' => $variant->getKey(),
                    'eans' => $eans ?? [],
                    'asins' => $formattedAsins ?? [],
                    'iwasku' => $variant->getIwasku(),
                    'productCode' => $variant->getProductCode(),
                    'variationSize' => $variant->getVariationSize(),
                    'color' => $colorInfo,
                    'customFieldTitle' => $customTableTitle,
                    'customField' => $variant->getCustomField(),
                    'published' => $variant->getPublished(),
                    //'bundleProducts' => $variantBundleProducts,
                    //'bundleProductCount' => $bundleProductCount,
                    //'hasBundleProducts' => $hasBundleProducts
                ];
            }
            return $variants;
        } catch (\Exception $e) {
            error_log('Get product variants error: ' . $e->getMessage());
            return [];
        }
    }

    private function getVariantBundleProducts(Product $variant): array
    {
        try {
            $bundleProducts = $variant->getBundleProducts();
            if (!$bundleProducts || !is_array($bundleProducts)) {
                return [];
            }
            return $this->formatBundleProducts($bundleProducts);
        } catch (\Exception $e) {
            error_log('Get variant bundle products error: ' . $e->getMessage());
            return [];
        }
    }

    private function formatBundleProducts(array $bundleProducts): array
    {
        $formattedBundleProducts = [];
        foreach ($bundleProducts as $bundleProduct) {
            if (!$bundleProduct) {
                continue;
            }
            $quantity = 1;
            $colorObject = method_exists($bundleProduct, 'getVariationColor') ? $bundleProduct->getVariationColor() : null;
            $variationColor = $colorObject ? $colorObject->getColor() : null;
            $formattedBundleProducts[] = [
                'id' => $bundleProduct->getId(),
                'key' => $bundleProduct->getKey() ?? '',
                'identifier' => $bundleProduct->getProductIdentifier() ?? '',
                'iwasku' => $bundleProduct->getIwasku() ?? '',
                'quantity' => $quantity,
                'published' => $bundleProduct->getPublished() ?? true,
                'size' => $bundleProduct->getVariationSize() ?? '',
                'color' => $variationColor,
                'customField' => $bundleProduct->getCustomField() ?? ''
            ];
        }
        return $formattedBundleProducts;
    }
}