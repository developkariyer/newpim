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
                    foreach ($sizeOptionsData as $sizeOption) {
                        if (is_array($sizeOption) && !empty($sizeOption[0])) {
                            $sizeName = $sizeOption[0];        
                            $measurement1 = $sizeOption[1];    
                            $measurement2 = $sizeOption[2];    
                            $measurement3 = $sizeOption[3];    
                            $label = $sizeName . " (" . $measurement1 . "x" . $measurement2 . "x" . $measurement3 . ")";
                            $options[] = [
                                'key' => $label,
                                'value' => $sizeName
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
