<?php

namespace App\Select;

use Pimcore\Model\DataObject\ClassDefinition\Data;
use Pimcore\Model\DataObject\ClassDefinition\DynamicOptionsProvider\SelectOptionsProviderInterface;
use Pimcore\Model\DataObject\Product;
use Pimcore\Model\DataObject\VariationColor;

class VariationColorSelect implements SelectOptionsProviderInterface
{

    public function getOptions(array $context, Data $fieldDefinition = null): array
    {
        $options = [];
        if (isset($context['object']) && $context['object'] instanceof Product) {
            $currentObject = $context['object'];
            if (!$currentObject->getObjectType() === 'virtual') {
                return $options;
            }
            if (!$currentObject->getParent() instanceof Product) {
                return $options;
            }
            $parentObject = $currentObject->getParent();
            $variationColors = $parentObject->getVariationColors();
            foreach ($variationColors as $variationColor) {
                if ($variationColor instanceof VariationColor) {
                    $colorName = $variationColor->getKey();
                    $options[] = [
                        'key' => $colorName,
                        'value' => $colorName
                    ];
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
