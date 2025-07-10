<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Pimcore\Model\DataObject\Product;
use Pimcore\Model\DataObject\Brand;
use Pimcore\Model\DataObject\Marketplace;


class DataProcessingService
{
    public function __construct(
        private LoggerInterface $logger
    ) {}

    private function buildProductData(Product $product): array
    {
        $variants = $this->getProductVariants($product);
        $variantAnalysis = $this->analyzeVariants($variants);
        return [
            'id' => $product->getId(),
            'name' => $product->getName(),
            'productIdentifier' => $product->getProductIdentifier(),
            'description' => $product->getDescription(),
            'categoryId' => $product->getProductCategory()?->getId(),
            'categoryName' => $product->getProductCategory()?->getKey(),
            'brands' => $this->formatObjectCollection($product->getBrandItems(), Brand::class),
            'marketplaces' => $this->formatObjectCollection($product->getMarketplaces(), Marketplace::class),
            'imagePath' => $product->getImage()?->getFullPath(),
            'hasVariants' => !empty($variants),
            'variants' => $variants,
            'variantColors' => array_values(array_unique($variantAnalysis['colors'], SORT_REGULAR)),
            'sizeTable' => $this->formatSizeTable($product, $variantAnalysis['usedSizes']),
            'customTable' => $this->formatCustomTable($product, $variantAnalysis['usedCustoms']),
            'usedSizes' => array_unique($variantAnalysis['usedSizes']),
            'usedCustoms' => array_values(array_unique($variantAnalysis['usedCustoms'])),
            'usedColorIds' => array_unique($variantAnalysis['usedColorIds']),
            'canEditSizeTable' => true,
            'canEditColors' => true,
            'canEditCustomTable' => true,
            'canCreateVariants' => true
        ];
    }

    private function getProductVariants(Product $product): array
    {
        $variants = [];
        $productVariants = $product->getChildren([Product::OBJECT_TYPE_VARIANT], true);
        foreach ($productVariants as $variant) {
            $variants[] = [
                'id' => $variant->getId(),
                'name' => $variant->getName(),
                'color' => $variant->getVariationColor()?->getColor(),
                'colorId' => $variant->getVariationColor()?->getId(),
                'size' => $variant->getVariationSize(),
                'custom' => $variant->getCustomField(),
                'published' => $variant->getPublished(), 
                'iwasku' => $variant->getIwasku(), 
                'productCode' => $variant->getProductCode()
            ];
        }
        return $variants;
    }

    private function analyzeVariants(array $variants): array
    {
        $colors = [];
        $usedSizes = [];
        $usedCustoms = [];
        $usedColorIds = [];
        foreach ($variants as $variant) {
            $isPublished = $variant['published'] ?? true;
            if ($variant['colorId']) {
                $usedColorIds[] = $variant['colorId'];
                $colors[] = [
                    'id' => $variant['colorId'],
                    'name' => $variant['color'],
                    'published' => $isPublished  
                ];
            }
            if ($variant['size']) {
                $usedSizes[] = $variant['size'];
            }
            if ($variant['custom']) {
                $usedCustoms[] = $variant['custom'];
            }
        }
        return [
            'colors' => $colors,
            'usedSizes' => $usedSizes,
            'usedCustoms' => $usedCustoms,
            'usedColorIds' => $usedColorIds
        ];
    }

    private function formatObjectCollection($items, string $expectedClass): array
    {
        if (!$items) {
            return [];
        }
        $result = [];
        $itemsArray = is_array($items) ? $items : [$items];
        foreach ($itemsArray as $item) {
            if ($item instanceof $expectedClass) {
                $result[] = [
                    'id' => $item->getId(),
                    'name' => $item->getKey()
                ];
            }
        }
        return $result;
    }

    private function formatSizeTable(Product $product, array $usedSizes): array
    {
        $sizeTableData = $product->getVariationSizeTable();
        if (!is_array($sizeTableData)) {
            return [];
        }
        $sizeTable = [];
        foreach ($sizeTableData as $row) {
            $beden = $row['beden'] ?? $row['label'] ?? '';
            $sizeTable[] = [
                'beden' => $beden,
                'en' => $row['en'] ?? $row['width'] ?? '',
                'boy' => $row['boy'] ?? $row['length'] ?? '',
                'yukseklik' => $row['yukseklik'] ?? $row['height'] ?? '',
                'locked' => in_array($beden, $usedSizes)
            ];
        }
        return $sizeTable;
    }

    private function formatCustomTable(Product $product, array $usedCustoms): array
    {
        $customTableData = $product->getCustomFieldTable();
        if (!is_array($customTableData)) {
            return [];
        }
        $customRows = [];
        $customTitle = '';
        foreach ($customTableData as $index => $row) {
            $deger = $row['deger'] ?? $row['value'] ?? '';
            if (!empty($deger) && $deger !== '[object Object]') {
                if ($index === 0) {
                    $customTitle = $deger;
                } else {
                    $customRows[] = [
                        'deger' => $deger,
                        'locked' => in_array($deger, $usedCustoms)
                    ];
                }
            }
        }
        return [
            'title' => $customTitle,
            'rows' => $customRows
        ];
    }

    public function processCustomTableData(string $customTableData): ?array
    {
        $decoded = json_decode($customTableData, true);
        if (!is_array($decoded) || !isset($decoded['rows']) || !is_array($decoded['rows'])) {
            return null;
        }
        $customFieldTable = [];
        $title = trim($decoded['title'] ?? '');
        if ($title !== '') {
            $customFieldTable[] = ['deger' => $title];
        }
        foreach ($decoded['rows'] as $row) {
            if (isset($row['deger']) && !empty($row['deger'])) {
                $customFieldTable[] = ['deger' => $row['deger']];
            }
        }
        return empty($customFieldTable) ? null : $customFieldTable;
    }

    public function removeTRChars(string $str): string
    {
        return str_ireplace(
            ['ı', 'İ', 'ğ', 'Ğ', 'ü', 'Ü', 'ş', 'Ş', 'ö', 'Ö', 'ç', 'Ç'], 
            ['i', 'I', 'g', 'G', 'u', 'U', 's', 'S', 'o', 'O', 'c', 'C'], 
            $str
        );    
    }

    public function sanitizeFolderName(string $name): string
    {
        $name = mb_strtolower($name);
        $name = str_replace(['ı', 'ğ', 'ü', 'ş', 'ö', 'ç'], ['i', 'g', 'u', 's', 'o', 'c'], $name);
        $name = preg_replace('/[^a-z0-9]/', '_', $name);
        $name = preg_replace('/_+/', '_', $name);
        $name = trim($name, '_');
        return $name ?: 'folder';
    }
}