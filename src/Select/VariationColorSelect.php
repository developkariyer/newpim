<?php

namespace App\Select;

use Pimcore\Model\DataObject\ClassDefinition\Data;
use Pimcore\Model\DataObject\ClassDefinition\DynamicOptionsProvider\SelectOptionsProviderInterface;
use Pimcore\Model\DataObject\Product;
use Pimcore\Model\DataObject\Color;

class VariationColorSelect implements SelectOptionsProviderInterface
{

    public function getOptions(array $context, Data $fieldDefinition = null): array
    {
        $options = [];
        if (isset($context['object']) && $context['object'] instanceof Product) {
            $currentObject = $context['object'];
            if ($currentObject->getType() !== \Pimcore\Model\DataObject::OBJECT_TYPE_VARIANT) {
                return $options;
            }
            $listingObject = new Color\Listing();
            $variationColors = $listingObject->load();
            if (!$variationColors) {
                return $options;
            }
            foreach ($variationColors as $variationColor) {
                if ($variationColor instanceof Color) {
                    $colorName = $variationColor->getColor();
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