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
                $sizeOptionsTable = $variantSizeTemplate->getSizeOptions();
                $sizeOptionsData = $sizeOptionsTable->getData();
                foreach ($sizeOptionsData as $sizeOption) {
                    $value = null;
                    $label = null;
                    $value = $sizeOption['key'] ?? $sizeOption['size'] ?? $sizeOption['value'] ?? null;
                    $label = $sizeOption['label'] ?? $sizeOption['name'] ?? $value;
                    if ($value !== null) { 
                        $options[] = [
                            'key'   => $label, 
                            'value' => $value  
                        ];
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
