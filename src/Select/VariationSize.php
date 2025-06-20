<?php

namespace App\Select;

use Pimcore\Model\DataObject\ClassDefinition\Data;
use Pimcore\Model\DataObject\ClassDefinition\DynamicOptionsProvider\SelectOptionsProviderInterface;
use Pimcore\Model\DataObject\Product;
use Pimcore\Model\DataObject\VariationSizeChart;

class VariationSize implements SelectOptionsProviderInterface
{
    public function getOptions(array $context, Data $fieldDefinition = null): array
    {
        $options = [];
        if (isset($context['object']) && $context['object'] instanceof Product) {
            $currentObject = $context['object'];
            $variantSizeTemplate = $currentObject->getVariantSizeTemplate();
            if (is_array($sizeOptionsData) && !empty($sizeOptionsData)) {
                foreach ($sizeOptionsData as $index => $sizeOption) {
                    error_log("Row $index: " . print_r($sizeOption, true));
                    
                    if (is_array($sizeOption)) {
                        // Mevcut key'leri göster
                        error_log("Available keys: " . implode(', ', array_keys($sizeOption)));
                        
                        // Tüm olası key kombinasyonlarını dene
                        $value = $sizeOption['key'] ?? $sizeOption['size'] ?? $sizeOption['value'] ?? 
                                $sizeOption['code'] ?? $sizeOption['name'] ?? null;
                        $label = $sizeOption['label'] ?? $sizeOption['description'] ?? 
                                $sizeOption['display'] ?? $sizeOption['text'] ?? $value;
                        
                        if ($value !== null) { 
                            $options[] = [
                                'key'   => $label, 
                                'value' => $value  
                            ];
                        }
                    }
                }
            }
        }

        return $options;
    }

    public function hasStaticOptions(array $context, Data $fieldDefinition): bool
    {
        return false;
    }

    public function getDefaultValue(array $context, Data $fieldDefinition): array|string|null
    {
        return null;
    }

}
