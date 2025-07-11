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
        private DataProcessingService $dataProcessor
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

    public function getObjectById(string $className, int $id): ?object
    {
        if (!class_exists($className)) {
            return null;
        }
        try {
            $object = $className::getById($id);
            return $object;
        } catch (\Exception $e) {
            return null;
        }
    }

    public function getFilteredProducts(
        int $limit, 
        int $offset, 
        ?string $categoryFilter = null, 
        string $searchQuery = '', 
        ?string $asinFilter = null, 
        ?string $brandFilter = null, 
        ?string $eanFilter = null
    ): array
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
            if (!empty($searchQuery) && strlen($searchQuery) >= self::MIN_SEARCH_LENGTH) { 
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

    public function formatProductForCatalog(Product $product): array
    {
        $variants = $product->getChildren([Product::OBJECT_TYPE_VARIANT], true);
        $variantsCount = count($variants);
        $image = $product->getImage();
        $imageThumbnail = null;
        if ($image) {
            try {
                $imageThumbnail = $image->getThumbnail('productList');
            } catch (\Exception $e) {
                // Ignore thumbnail error
            }
        }
        $category = $product->getProductCategory();
        return [
            'id' => $product->getId(),
            'name' => $product->getName(),
            'identifier' => $product->getProductIdentifier(),
            'image' => $imageThumbnail ? $imageThumbnail->getUrl() : null,
            'category' => $category ? $category->getCategory() : null,
            'createdAt' => $product->getCreationDate(),
            'description' => $product->getDescription(),
            'variantsCount' => $variantsCount,
            'hasVariants' => $variantsCount > 0
        ];
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

    public function getParentProductIdsByVariantAsin(string $asin): array
    {
        try {
            $asin = trim($asin);
            $listing = new AsinListing();
            $listing->setCondition("published = 1 AND asin = ?", [$asin]);
            $asins = $listing->load();
            if (empty($asins)) {
                return [];
            }
            $productIds = [];
            foreach ($asins as $asinObj) {
                $productObjs = $asinObj->getProducts();
                if ($productObjs) {
                    foreach ($productObjs as $product) {
                        if ($product->getType() === 'variant') {
                            $parent = $product->getParent();
                            if ($parent && $parent instanceof Product) {
                                $productIds[$parent->getId()] = $parent->getId();
                            }
                        } else {
                            $productIds[$product->getId()] = $product->getId();
                        }
                    }
                }
            }
            return array_values($productIds);
        } catch (\Exception $e) {
            return [];
        }
    }

    public function getParentProductIdsByVariantBrand(string $brand): array
    {
        try {
            $brand = trim($brand);
            $listing = new BrandListing();
            $listing->setCondition("published = 1 AND LOWER(name) LIKE ?", ["%" . strtolower($brand) . "%"]);
            $brands = $listing->load();
            if (empty($brands)) {
                return [];
            }
            $productIds = [];
            foreach ($brands as $brandObj) {
                $products = new ProductListing();
                $products->setCondition("o_id IN (SELECT src_id FROM object_relations_product WHERE dest_id = ? AND fieldname = 'brandItems')", [$brandObj->getId()]);
                $productObjs = $products->load();
                foreach ($productObjs as $product) {
                    $productIds[$product->getId()] = $product->getId();
                    $variants = $product->getChildren([Product::OBJECT_TYPE_VARIANT], true);
                    foreach ($variants as $variant) {
                        $parent = $variant->getParent();
                        if ($parent && $parent instanceof Product) {
                            $productIds[$parent->getId()] = $parent->getId();
                        }
                    }
                }
            }
            return array_values($productIds);
        } catch (\Exception $e) {
            return [];
        }
    }

    public function getParentProductIdsByVariantEan(string $ean): array
    {
        try {
            $ean = trim($ean);
            $listing = new EanListing();
            $listing->setCondition("published = 1 AND ean = ?", [$ean]);
            $eans = $listing->load();
            if (empty($eans)) {
                return [];
            }
            $productIds = [];
            foreach ($eans as $eanObj) {
                $productObjs = $eanObj->getProducts();
                if ($productObjs) {
                    foreach ($productObjs as $product) {
                        if ($product->getType() === 'variant') {
                            $parent = $product->getParent();
                            if ($parent && $parent instanceof Product) {
                                $productIds[$parent->getId()] = $parent->getId();
                            }
                        } else {
                            $productIds[$product->getId()] = $product->getId();
                        }
                    }
                }
            }
            return array_values($productIds);
        } catch (\Exception $e) {
            return [];
        }
    }

    public function getProductVariants(Product $product, $customTableTitle): array
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

}