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
                $sizeOptions = $variantSizeTemplate->getSizeOptions();
                if (is_array($sizeOptions)) {
                    foreach ($sizeOptions as $sizeOption) {
                        if (is_object($sizeOption)) {
                            $value = $sizeOption->getSize();
                            $label = $sizeOption->getLabel() ?: $value; 
                            
                            if ($value) {
                                $options[] = [
                                    'key' => $label,
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
