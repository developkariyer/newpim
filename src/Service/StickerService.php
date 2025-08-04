<?php

namespace App\Service;

use Pimcore\Model\DataObject\Product;
use Pimcore\Model\DataObject\GroupProduct;
use Pimcore\Model\Asset;
use Psr\Log\LoggerInterface;
use Exception;

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
        return [
            'product_id' => $product->getId(),
            'product_name' => $product->getName() ?? '',
            'category' => $product->getProductCategory() ?? '',
            'image_link' => $product->getImage()->getFullPath() ?? '',
            'product_identifier' => $product->getProductIdentifier() ?? '',
            'iwasku' => $product->getIwasku() ?? '',
            'ean_count' => $eanInfo['count'],
            'eans' => $eanInfo['list'],
            'sticker_status' => $stickerStatus
        ];
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
        $searchFields = [
            $product->getName(),
            $product->getProductCategory(),
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
}