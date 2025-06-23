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
            if (!$variantSizeTemplate && $currentObject->getParent() instanceof Product) {
                $parentObject = $currentObject->getParent();
                $variantSizeTemplate = $parentObject->getVariantSizeTemplate();
            }
            
            if ($variantSizeTemplate instanceof VariationSizeChart) {
                $sizeOptionsData = $variantSizeTemplate->getSizeOptions();
                if (is_array($sizeOptionsData) && !empty($sizeOptionsData)) {
                    $dataRows = array_slice($sizeOptionsData, 1);
                    foreach ($dataRows as $sizeOption) {
                        if (is_array($sizeOption) && !empty($sizeOption[0])) {
                            $sizeName = isset($sizeOption[0]) ? $sizeOption[0] : '';        
                            $measurement1 = isset($sizeOption[1]) ? $sizeOption[1] : '';    
                            $measurement2 = isset($sizeOption[2]) ? $sizeOption[2] : '';    
                            $measurement3 = isset($sizeOption[3]) ? $sizeOption[3] : '';    
                            $label = $sizeName;
                            if (!empty($measurement1) || !empty($measurement2) || !empty($measurement3)) {
                                $label .= " (" . $measurement1 . "x" . $measurement2 . "x" . $measurement3 . ")";
                            }
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
