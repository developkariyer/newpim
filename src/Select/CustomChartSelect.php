<?php

namespace App\Select;

use Pimcore\Model\DataObject\ClassDefinition\Data;
use Pimcore\Model\DataObject\ClassDefinition\DynamicOptionsProvider\SelectOptionsProviderInterface;
use Pimcore\Model\DataObject\Product;
use Pimcore\Model\DataObject\CustomChart;

class CustomChartSelect implements SelectOptionsProviderInterface
{
    public function getOptions(array $context, Data $fieldDefinition = null): array
    {
        $options = [];
        if (isset($context['object']) && $context['object'] instanceof Product) {
            $currentObject = $context['object'];
            $customSizeTemplate = $currentObject->getCustomVariantTemplate();
            
            if ($customSizeTemplate instanceof CustomChart) {
                $customOptionsData = $customSizeTemplate->getCustomOptions();
                
                if (is_array($customOptionsData) && !empty($customOptionsData)) {
                    $dataRows = array_slice($customOptionsData, 1);
                    foreach ($dataRows as $customOption) {
                        if (is_array($customOption) && !empty($customOption[0])) {
                            $optionValue = $customOption[0];
                            $options[] = [
                                'key' => $optionValue,
                                'value' => $optionValue
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
