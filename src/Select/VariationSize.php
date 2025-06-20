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
        error_log('Context keys: ' . implode(', ', array_keys($context)));
        if (isset($context['object']) && $context['object'] instanceof Product) {
            $currentObject = $context['object'];
            error_log('Current object ID: ' . $currentObject->getId());
            $variantSizeTemplate = $currentObject->getVariantSizeTemplate();
            error_log('Variant size template: ' . ($variantSizeTemplate ? $variantSizeTemplate->getId() : 'NULL'));
            if ($variantSizeTemplate instanceof VariationSizeChart) {
                $sizeOptionsData = $variantSizeTemplate->get('sizeOptions');
                error_log('Direct get sizeOptions: ' . print_r($sizeOptionsData, true));
                $sizeOptionsGetter = $variantSizeTemplate->getSizeOptions();
                error_log('Getter sizeOptions: ' . print_r($sizeOptionsGetter, true));
                $options[] = [
                    'key' => 'Test Size',
                    'value' => 'test'
                ];
            } else {
                error_log('variantSizeTemplate is not VariationSizeChart instance');
            }
        } else {
            error_log('Object is not Product instance or not set');
        }
        error_log('Final options count: ' . count($options));
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
