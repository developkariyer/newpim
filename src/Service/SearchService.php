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

//    public function getObjectById(string $className, int $id): ?object
//    {
//        if (!class_exists($className)) {
//            return null;
//        }
//        try {
//            $object = $className::getById($id);
//            return $object;
//        } catch (\Exception $e) {
//            return null;
//        }
//    }

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

    private function getParentProductIdsByVariantAsin(string $asinValue): array
    {
        $asinListing = new AsinListing();
        $asinListing->setCondition("LOWER(asin) LIKE LOWER(?) OR LOWER(fnskus) LIKE LOWER(?)", ["%$asinValue%", "%$asinValue%"]);
        $asinObject = $asinListing->getCurrent();
        if (!$asinObject) {
            return [];
        }
        $variantListing = new ProductListing();
        $variantListing->setCondition("type = 'variant' AND published = 1 AND asin__id = ?", [$asinObject->getId()]);
        $variants = $variantListing->getObjects();
        $parentIds = [];
        foreach ($variants as $variant) {
            $parentIds[] = $variant->getParentId();
        }
        return array_unique(array_filter($parentIds));




        // try {
        //     $variantListing = new ProductListing();
        //     $variantListing->setCondition("type = 'variant' AND published = 1");
        //     $variants = $variantListing->getObjects();
        //     $parentIds = [];
        //     foreach ($variants as $variant) {
        //         $asinObjects = $variant->getAsin();
        //         if ($asinObjects) {
        //             if (is_array($asinObjects)) {
        //                 foreach ($asinObjects as $asinObj) {
        //                     if ($asinObj->getAsin() && stripos($asinObj->getAsin(), $asinValue) !== false) {
        //                         $parentIds[] = $variant->getParentId();
        //                         break;
        //                     }
        //                     if ($asinObj->getFnskus() && stripos($asinObj->getFnskus(), $asinValue) !== false) {
        //                         $parentIds[] = $variant->getParentId();
        //                         break;
        //                     }
        //                 }
        //             } else {
        //                 if ($asinObjects->getAsin() && stripos($asinObjects->getAsin(), $asinValue) !== false) {
        //                     $parentIds[] = $variant->getParentId();
        //                 }
        //                 if ($asinObjects->getFnskus() && stripos($asinObjects->getFnskus(), $asinValue) !== false) {
        //                     $parentIds[] = $variant->getParentId();
        //                 }
        //             }
        //         }
        //     }
        //     return array_unique(array_filter($parentIds));
        // } catch (\Exception $e) {
        //     error_log('Get parent product IDs by variant ASIN error: ' . $e->getMessage());
        //     return [];
        // }
    }

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
                ];
            }
            return $variants;
        } catch (\Exception $e) {
            error_log('Get product variants error: ' . $e->getMessage());
            return [];
        }
    }

}