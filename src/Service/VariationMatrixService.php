<?php

namespace App\Service;

use Pimcore\Model\DataObject\Product;
use Pimcore\Model\DataObject\VariationSizeChart;
use Pimcore\Model\DataObject\CustomChart;
use Pimcore\Model\DataObject\Data\StructuredTable;

class VariationMatrixService
{
    public function generateMatrix(Product $product): array
    {
        $sizes = $this->getSizeOptions($product);
        $colors = $this->getColorOptions($product);
        $customs = $this->getCustomOptions($product);

        error_log("DEBUG sizes: " . print_r($sizes, true));
        error_log("DEBUG colors: " . print_r($colors, true));
        error_log("DEBUG customs: " . print_r($customs, true));

        if (empty($sizes) || empty($colors)) {
            error_log("DEBUG: sizes or colors is empty, matrix will not be generated.");
            return [];
        }

        $existingMatrix = $product->getVariationMatrix();
        error_log("DEBUG: existingMatrix type: " . (is_object($existingMatrix) ? get_class($existingMatrix) : gettype($existingMatrix)));

        if ($existingMatrix instanceof StructuredTable) {
            $existingData = $existingMatrix->getData();
            error_log("DEBUG: existingMatrix is StructuredTable, data: " . print_r($existingData, true));
            $hasRealData = false;
            if (is_array($existingData)) {
                foreach ($existingData as $row) {
                    if (
                        (!empty($row['size']) || !empty($row['color']) || !empty($row['custom']))
                        && isset($row['isactive'])
                    ) {
                        $hasRealData = true;
                        break;
                    }
                }
            }
            if ($hasRealData) {
                error_log("DEBUG: existingMatrix already has real data, returning empty array.");
                return [];
            }
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
                            'isactive' => false
                        ];
                    }
                } else {
                    $matrix[] = [
                        'size' => $size['value'],
                        'color' => $color['key'],
                        'custom' => '',
                        'isactive' => false
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