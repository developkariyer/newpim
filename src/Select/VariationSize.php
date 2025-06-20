<?php

namespace App\Select;

use Pimcore\Model\DataObject\ClassDefinition\Data;
use Pimcore\Model\DataObject\ClassDefinition\DynamicOptionsProvider\SelectOptionsProviderInterface;
use Pimcore\Model\DataObject;

class VariationSize implements SelectOptionsProviderInterface
{
    public function getOptions(array $context, Data $fieldDefinition = null): array
    {
        $options = [];
        if (isset($context['object']) && $context['object'] instanceof DataObject\Concrete) {
            $currentObject = $context['object'];
            $variantSizeTemplate = $currentObject->getVariantSizeTemplate();
            if ($variantSizeTemplate instanceof DataObject\Concrete) {
                $sizeOptionsTable = $variantSizeTemplate->getSizeOptions();
                if ($sizeOptionsTable instanceof DataObject\Data\Table) {
                    $sizeOptionsData = $sizeOptionsTable->getData();

                    if (is_array($sizeOptionsData) && !empty($sizeOptionsData)) {
                        foreach ($sizeOptionsData as $sizeOption) {
                            $value = null;
                            $label = null;

                            if (is_array($sizeOption)) {
                                $value = $sizeOption['key'] ?? $sizeOption['size'] ?? $sizeOption['value'] ?? null;
                                $label = $sizeOption['label'] ?? $sizeOption['name'] ?? $value;
                            } 
                            elseif (is_object($sizeOption)) {
                                $value = method_exists($sizeOption, 'getKey') ? $sizeOption->getKey() : 
                                        (method_exists($sizeOption, 'getSize') ? $sizeOption->getSize() : 
                                        (method_exists($sizeOption, 'getValue') ? $sizeOption->getValue() : null));
                                $label = method_exists($sizeOption, 'getLabel') ? $sizeOption->getLabel() : 
                                        (method_exists($sizeOption, 'getName') ? $sizeOption->getName() : $value);
                            }

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
