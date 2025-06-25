<?php

namespace App\Service;

use Pimcore\Model\DataObject\Product;
use Pimcore\Model\DataObject\VariationSizeChart;
use Pimcore\Model\DataObject\CustomChart;

class VariationMatrixService
{
    public function generateMatrix(Product $product): array
    {
        $sizes = $this->getSizeOptions($product);
        $colors = $this->getColorOptions($product);
        $customs = $this->getCustomOptions($product);

        if (empty($sizes) || empty($colors)) {
            return [];
        }
        $matrix = [];
        foreach ($sizes as $size) {
            foreach ($colors as $color) {
                if (!empty($customs)) {
                    foreach ($customs as $custom) {
                        $matrix[] = [
                            'size' => $size['value'],
                            'color' => $color['key'], 
                            'custom' => $custom['value'],
                            'isActive' => true
                        ];
                    }
                } else {
                    $matrix[] = [
                        'size' => $size['value'],
                        'color' => $color['key'],
                        'custom' => '',
                        'isActive' => true
                    ];
                }
            }
        }
        return $matrix;
    }

    private function createSingleVariant(Product $parentProduct, array $combination): ?Product
    {
        try {
            $keyParts = [
                $parentProduct->getKey(),
                $combination['color'],
                $combination['size']
            ];
            if (!empty($combination['custom'])) {
                $keyParts[] = $combination['custom'];
            }
            $variantKey = implode('_', array_filter($keyParts));
            $existingVariant = Product::getByPath($parentProduct->getFullPath() . '/' . $variantKey);
            if ($existingVariant) {
                return $existingVariant; 
            }
            $variant = new Product();
            $variant->setKey($variantKey);
            $variant->setParent($parentProduct); 
            $variant->setPublished(true);
            $variantName = $parentProduct->getName() . ' - ' . $combination['color'] . ' - ' . $combination['size'];
            if (!empty($combination['custom'])) {
                $variantName .= ' - ' . $combination['custom'];
            }
            $variant->setName($variantName);
            $variant->setDescription($parentProduct->getDescription());
            $variant->setCategory($parentProduct->getCategory());
            $variant->setBrands($parentProduct->getBrands());
            $variant->setMarketplaces($parentProduct->getMarketplaces());
            $variant->setVariationColor($combination['color']);
            $variant->setVariationSize($combination['size']);
            $variant->setObjectType('actual');
            if (!empty($combination['custom']) && method_exists($variant, 'setCustomSelect')) {
                $variant->setCustomSelect($combination['custom']);
            }
            $variant->save();
            return $variant;
            
        } catch (\Exception $e) {
            error_log('Varyant oluşturma hatası: ' . $e->getMessage());
            return null;
        }
    }

    public function createVariants(Product $product): array
    {
        $matrix = $this->generateMatrix($product);
        $createdVariants = [];
        foreach ($matrix as $combination) {
            if ($combination['isActive']) {
                $variant = $this->createSingleVariant($product, $combination);
                if ($variant) {
                    $createdVariants[] = $variant;
                }
            }
        }
        
        return $createdVariants;
    }
    
    private function getSizeOptions(Product $product): array
    {
        $sizes = [];
        $variationSizeTemplate = $product->getVariantSizeTemplate();
        if ($variationSizeTemplate instanceof VariationSizeChart) {
            $sizeOptionsData = $variationSizeTemplate->getSizeOptions();
            if (is_array($sizeOptionsData) && !empty($sizeOptionsData)) {
                $dataRows = array_slice($sizeOptionsData, 1);
                foreach ($dataRows as $sizeOption) {
                    if (is_array($sizeOption) && !empty($sizeOption[0])) {
                        $sizeName = $sizeOption[0] ?? '';
                        $width = $sizeOption[1] ?? '';
                        $lenght = $sizeOption[2] ?? '';
                        $weight = $sizeOption[3] ?? '';
                        $label = $sizeName;
                        if (!empty($width) || !empty($lenght) || !empty($weight)) {
                            $label .= " ({$width}x{$lenght}x{$weight})";
                        }
                        $sizes[] = [
                            'key' => $label,
                            'value' => $sizeName
                        ];
                    }
                }
            }
        }
        return $sizes;
    }
    
    private function getColorOptions(Product $product): array
    {
        $colors = [];
        $variationColors = $product->getVariationColors();
        if (is_array($variationColors) && !empty($variationColors)) {
            foreach ($variationColors as $colorObject) {
                if ($colorObject && method_exists($colorObject, 'getKey')) {
                    $colors[] = [
                        'key' => $colorObject->getKey(),
                        'value' => $colorObject->getKey()
                    ];
                }
            }
        }
        return $colors;
    }
    
    private function getCustomOptions(Product $product): array
    {
        $customs = [];
        $customVariantTemplate = $product->getCustomVariantTemplate();
        if ($customVariantTemplate instanceof CustomChart) {
            $customOptionsData = $customVariantTemplate->getCustomOptions();
            if (is_array($customOptionsData) && !empty($customOptionsData)) {
                $dataRows = array_slice($customOptionsData, 1);
                foreach ($dataRows as $customOption) {
                    if (is_array($customOption) && !empty($customOption[0])) {
                        $customValue = $customOption[0];
                        $customs[] = [
                            'key' => $customValue,
                            'value' => $customValue
                        ];
                    }
                }
            }
        }
        return $customs;
    }
}