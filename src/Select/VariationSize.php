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
            if ($variantSizeTemplate instanceof VariationSizeChart) {
                $sizeOptionsData = $variantSizeTemplate->getSizeOptions();
                if (is_array($sizeOptionsData) && !empty($sizeOptionsData)) {
                    foreach ($sizeOptionsData as $index => $sizeOption) {
                        if (is_array($sizeOption)) {
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
        }

        error_log('Final options: ' . print_r($options, true));
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
