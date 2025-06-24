<?php

namespace App\Service;

use App\Model\DataObject\Product;
use Pimcore\Model\DataObject\VariationSizeChart;
use Pimcore\Model\DataObject\CustomChart;
use Pimcore\Model\DataObject\Data\StructuredTable;

class VariationMatrixService
{
    public function generateMatrix(Product $product): array
    {
        $existingMatrix = $product->getVariationMatrix();
        if ($existingMatrix instanceof StructuredTable) {
            $existingData = $existingMatrix->getData();
            if (is_array($existingData) && !empty($existingData)) {
                return [];
            }
        }
        elseif (is_array($existingMatrix) && !empty($existingMatrix)) {
            return [];
        }
        $matrix = [];
        $sizes = $this->getSizeOptions($product);
        $colors = $this->getColorOptions($product);
        $customs = $this->getCustomOptions($product);
        error_log("DEBUG sizes: " . print_r($sizes, true));
        error_log("DEBUG colors: " . print_r($colors, true));
        error_log("DEBUG customs: " . print_r($customs, true));
        if (empty($sizes) || empty($colors)) {
            return [];
        }
        foreach ($sizes as $size) {
            foreach ($colors as $color) {
                if (!empty($customs)) {
                    foreach ($customs as $custom) {
                        $matrix[] = [
                            'size' => $size['value'],
                            'color' => $color['key'], 
                            'custom' => $custom['value'],
                            'isActive' => false
                        ];
                    }
                } else {
                    $matrix[] = [
                        'size' => $size['value'],
                        'color' => $color['key'],
                        'custom' => '',
                        'isActive' => false
                    ];
                }
            }
        }
        return $matrix;
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