<?php

namespace App\Service;

use Pimcore\Model\DataObject\Product;
use Pimcore\Model\DataObject\GroupProduct;
use Pimcore\Model\Asset;
use Psr\Log\LoggerInterface;
use Exception;
use App\Utils\PdfGenerator;

class StickerService
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function getGroupStickers(int $groupId, array $params = []): array
    {
        try {
            $group = GroupProduct::getById($groupId);
            if (!$group) {
                throw new \InvalidArgumentException("Group with ID {$groupId} not found");
            }
            $page = $params['page'] ?? 1;
            $limit = $params['limit'] ?? 5;
            $searchTerm = $params['searchTerm'] ?? null;
            $products = $this->getProductsFromGroup($group, $searchTerm);
            $paginatedProducts = $this->applyPagination($products, $page, $limit);
            $groupedStickers = [];
            foreach ($paginatedProducts['data'] as $product) {
                $stickerInfo = $this->formatProductStickerInfo($product);
                $productIdentifier = $product->getProductIdentifier() ?? 'unknown';
                $groupedStickers[$productIdentifier][] = $stickerInfo;
            }
            return [
                'stickers' => $groupedStickers,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $limit,
                    'total_items' => $paginatedProducts['total'],
                    'total_pages' => ceil($paginatedProducts['total'] / $limit)
                ]
            ];
        } catch (Exception $e) {
            $this->logger->error('Error getting group stickers', [
                'group_id' => $groupId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function checkStickerStatus(Product $product): array
    {
        $status = [
            'has_eu_sticker' => false,
            'has_iwasku_sticker' => false,
            'eu_sticker_count' => 0,
            'iwasku_sticker_count' => 0,
            'eu_sticker_links' => [],
            'iwasku_sticker_links' => []
        ];
        try {
            $euStickers = $product->getSticker4x6eu();
            if ($euStickers) {
                $status = $this->processStickerAssets($euStickers, $status, 'eu');
            }
            $iwaskuStickers = $product->getSticker4x6iwasku();
            if ($iwaskuStickers) {
                $status = $this->processStickerAssets($iwaskuStickers, $status, 'iwasku');
            }
        } catch (Exception $e) {
            $this->logger->error('Error checking sticker status', [
                'product_id' => $product->getId(),
                'error' => $e->getMessage()
            ]);
        }
        return $status;
    }

    public function formatProductStickerInfo(Product $product): array
    {
        $eanInfo = $this->getProductEanInfo($product);
        $stickerStatus = $this->checkStickerStatus($product);
        $imageUrl = '';
        try {
            $imageAsset = $product->getImage();
            if ($imageAsset instanceof Asset) {
                $imageUrl = $imageAsset->getFullPath();
            }
        } catch (Exception $e) {
            $this->logger->warning('Error getting product image', [
                'product_id' => $product->getId(),
                'error' => $e->getMessage()
            ]);
        }
        $productCategory = '';
        $categoryObject = $product->getProductCategory();
        $productCategory = $categoryObject ? $categoryObject->getCategory() : '';
        return [
            'product_id' => $product->getId(),
            'product_name' => $product->getName() ?? '',
            'category' => $productCategory ?? '',
            'image_link' => $imageUrl, 
            'product_identifier' => $product->getProductIdentifier() ?? '',
            'iwasku' => $product->getIwasku() ?? '',
            'ean_count' => $eanInfo['count'],
            'eans' => $eanInfo['list'],
            'sticker_status' => $stickerStatus
        ];
    }

    public function getProductDetails(string $productIdentifier, int $groupId): array
    {
        try {
            $group = GroupProduct::getById($groupId);
            if (!$group) {
                throw new \InvalidArgumentException("Group with ID {$groupId} not found");
            }
            $products = [];
            $relatedProducts = $group->getProducts();
            if ($relatedProducts) {
                foreach ($relatedProducts as $product) {
                    if ($product instanceof Product && 
                        $product->getPublished() && 
                        $product->getProductIdentifier() === $productIdentifier) {
                        
                        $productData = $this->formatProductDetailInfo($product);
                        $products[] = $productData;
                    }
                }
            }
            return [
                'success' => true,
                'products' => $products
            ];
        } catch (Exception $e) {
            $this->logger->error('Error getting product details', [
                'product_identifier' => $productIdentifier,
                'group_id' => $groupId,
                'error' => $e->getMessage()
            ]);
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    private function getProductEanInfo(Product $product): array
    {
        $eanList = [];
        $totalEans = 0;
        try {
            $eans = $product->getEans();
            if ($eans && count($eans) > 0) {
                foreach ($eans as $eanObject) {
                    if ($eanObject && method_exists($eanObject, 'getGTIN') && $eanObject->getGTIN()) {
                        $eanList[] = $eanObject->getGTIN();
                        $totalEans++;
                    }
                }
            }
        } catch (Exception $e) {
            $this->logger->warning('Error getting EAN info', [
                'product_id' => $product->getId(),
                'error' => $e->getMessage()
            ]);
        }
        return [
            'count' => $totalEans,
            'list' => $eanList
        ];
    }
    
    private function formatProductDetailInfo(Product $product): array
    {
        $eanInfo = $this->getProductEanInfo($product);
        $stickerLinks = $this->getStickerLinks($product);
        $imageUrl = '';
        try {
            $imageAsset = $product->getImage();
            if ($imageAsset instanceof Asset) {
                $imageUrl = $imageAsset->getFullPath();
            }
        } catch (Exception $e) {
            $this->logger->warning('Error getting product image in details', [
                'product_id' => $product->getId(),
                'error' => $e->getMessage()
            ]);
        }
        $productCategory = '';
        $categoryObject = $product->getProductCategory();
        $productCategory = $categoryObject ? $categoryObject->getCategory() : '';
        return [
            'iwasku' => $product->getIwasku() ?? '',
            'dest_id' => $product->getId(),
            'name' => $product->getName() ?? '',
            'productCode' => $product->getProductCode() ?? '',
            'productCategory' => $productCategory ?? '',
            'imageUrl' => $imageUrl,
            'variationSize' => $product->getVariationSize() ?? '',
            'variationColor' => $product->getVariationColor() ? $product->getVariationColor()->getColor() : '',
            'productIdentifier' => $product->getProductIdentifier() ?? '',
            'ean_count' => $eanInfo['count'],
            'eans' => $eanInfo['list'],
            'sticker_link_eu' => $stickerLinks['eu'],
            'sticker_link' => $stickerLinks['iwasku']
        ];
    }

    private function getStickerLinks(Product $product): array
    {
        $links = [
            'eu' => '',
            'iwasku' => ''
        ];
        try {
            $euStickers = $product->getSticker4x6eu();
            if ($euStickers) {
                if (is_array($euStickers) && count($euStickers) > 0) {
                    $firstSticker = $euStickers[0];
                    if ($firstSticker instanceof Asset) {
                        $links['eu'] = $firstSticker->getFullPath();
                    }
                } elseif ($euStickers instanceof Asset) {
                    $links['eu'] = $euStickers->getFullPath();
                }
            }
            $iwaskuStickers = $product->getSticker4x6iwasku();
            if ($iwaskuStickers) {
                if (is_array($iwaskuStickers) && count($iwaskuStickers) > 0) {
                    $firstSticker = $iwaskuStickers[0];
                    if ($firstSticker instanceof Asset) {
                        $links['iwasku'] = $firstSticker->getFullPath();
                    }
                } elseif ($iwaskuStickers instanceof Asset) {
                    $links['iwasku'] = $iwaskuStickers->getFullPath();
                }
            }
        } catch (Exception $e) {
            $this->logger->warning('Error getting sticker links', [
                'product_id' => $product->getId(),
                'error' => $e->getMessage()
            ]);
        }

        return $links;
    }

    private function getProductsFromGroup(GroupProduct $group, ?string $searchTerm = null): array
    {
        $products = [];
        if (method_exists($group, 'getProducts')) {
            $relatedProducts = $group->getProducts();
            if ($relatedProducts) {
                foreach ($relatedProducts as $product) {
                    if ($product instanceof Product && $product->getPublished()) {
                        if ($searchTerm && !$this->matchesSearchTerm($product, $searchTerm)) {
                            continue;
                        }
                        $products[] = $product;
                    }
                }
            }
        }
        return $products;
    }

    private function matchesSearchTerm(Product $product, string $searchTerm): bool
    {
        $productCategory = '';
        $categoryObject = $product->getProductCategory();
        $productCategory = $categoryObject ? $categoryObject->getCategory() : '';
        $searchFields = [
            $product->getName(),
            $productCategory,
            $product->getProductIdentifier(),
            $product->getIwasku()
        ];
        $searchTerm = strtolower($searchTerm);
        foreach ($searchFields as $field) {
            if ($field && strpos(strtolower($field), $searchTerm) !== false) {
                return true;
            }
        }
        return false;
    }

    private function applyPagination(array $products, int $page, int $limit): array
    {
        $total = count($products);
        $offset = ($page - 1) * $limit;
        $paginatedData = array_slice($products, $offset, $limit);
        return [
            'data' => $paginatedData,
            'total' => $total
        ];
    }

    private function processStickerAssets($stickers, array $status, string $type): array
    {
        $typePrefix = $type === 'eu' ? 'eu_sticker' : 'iwasku_sticker';
        if (is_array($stickers)) {
            $status["has_{$typePrefix}"] = count($stickers) > 0;
            $status["{$typePrefix}_count"] = count($stickers);
            foreach ($stickers as $sticker) {
                if ($sticker instanceof Asset) {
                    $status["{$typePrefix}_links"][] = $sticker->getFullPath();
                }
            }
        } else if ($stickers instanceof Asset) {
            $status["has_{$typePrefix}"] = true;
            $status["{$typePrefix}_count"] = 1;
            $status["{$typePrefix}_links"][] = $stickers->getFullPath();
        }
        return $status;
    }

    // Sticker Add Methods
    public function addStickerToGroup(string $productId, int $groupId): array
    {
        try {
            $group = GroupProduct::getById($groupId);
            if (!$group) {
                return [
                    'success' => false,
                    'message' => 'Etiket grubu bulunamadı.'
                ];
            }
            $productListing = new Product\Listing();
            $productListing->setCondition('iwasku = ?', $productId);
            $productListing->setLimit(1);
            $products = $productListing->load();
            if (count($products) === 0) {
                return [
                    'success' => false,
                    'message' => 'Bu ürün Pimcore\'da bulunamadı.'
                ];
            }
            $product = $products[0];
            $existingProducts = $group->getProducts() ?? [];
            if (in_array($product, $existingProducts, true)) {
                return [
                    'success' => false,
                    'message' => 'Bu ürün zaten bu grupta bulunmaktadır.'
                ];
            }
            $this->createStickersForProduct($product);
            $group->setProducts(array_merge($existingProducts, [$product]));
            $group->save();
            return [
                'success' => true,
                'message' => 'Etiket başarıyla eklendi ve oluşturuldu.',
                'product_id' => $product->getId(),
                'iwasku' => $product->getIwasku()
            ];
        } catch (Exception $e) {
            $this->logger->error('Error adding sticker to group', [
                'product_id' => $productId,
                'group_id' => $groupId,
                'error' => $e->getMessage()
            ]);
            return [
                'success' => false,
                'message' => 'Etiket eklenirken bir hata oluştu: ' . $e->getMessage()
            ];
        }
    }

    private function createStickersForProduct(Product $product): void
    {
        try {
            $existingEuStickers = $product->getSticker4x6eu();
            if (!$existingEuStickers) {
                $this->createEuSticker($product);
            }
            $existingIwaskuStickers = $product->getSticker4x6iwasku();
            if (!$existingIwaskuStickers) {
                $this->createIwaskuSticker($product);
            }
        } catch (Exception $e) {
            $this->logger->error('Error creating stickers for product', [
                'product_id' => $product->getId(),
                'iwasku' => $product->getIwasku(),
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    private function createEuSticker(Product $product): void
    {
        try {
            $filename = "eu_sticker_{$product->getIwasku()}.pdf";
            $euAsset = PdfGenerator::generate4x6eu($product, $filename);
            $product->setSticker4x6eu($euAsset);
            $product->save();
            $this->logger->info('EU sticker created for product', [
                'product_id' => $product->getId(),
                'iwasku' => $product->getIwasku(),
                'filename' => $filename
            ]);
        }
        catch (Exception $e) {
            $this->logger->error('Error creating EU sticker', [
                'product_id' => $product->getId(),
                'iwasku' => $product->getIwasku(),
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    private function createIwaskuSticker(Product $product): void
    {
        try {
            $filename = "iwasku_sticker_{$product->getIwasku()}.pdf";
            $iwaskuAsset = PdfGenerator::generate4x6iwasku($product, $filename);
            $product->setSticker4x6iwasku($iwaskuAsset);
            $product->save();
            $this->logger->info('IWASKU sticker created for product', [
                'product_id' => $product->getId(),
                'iwasku' => $product->getIwasku(),
                'filename' => $filename
            ]);
           
        } catch (Exception $e) {
            $this->logger->error('Error creating IWASKU sticker', [
                'product_id' => $product->getId(),
                'iwasku' => $product->getIwasku(),
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
}