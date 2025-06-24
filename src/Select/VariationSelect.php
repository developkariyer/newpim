<?php

namespace App\Select;

use Pimcore\Model\DataObject\ClassDefinition\Data;
use Pimcore\Model\DataObject\ClassDefinition\DynamicOptionsProvider\SelectOptionsProviderInterface;
use Pimcore\Model\DataObject\Product;
use App\Service\VariationMatrixService;
use Pimcore\Container;

class VariationSelect implements SelectOptionsProviderInterface
{

    private VariationMatrixService $variationMatrixService;

    public function __construct()
    {
        $this->variationMatrixService = Container::get(VariationMatrixService::class);
    }

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
            $matrixData = $this->variationMatrixService->generateMatrix($parentProduct);
        
            foreach ($matrixData as $index => $row) {
                $label = trim($row['size'] . ' ' . $row['color'] . ' ' . $row['custom']);
                $label = preg_replace('/\s+/', ' ', $label);
                $value = $row['size'] . '_' . $row['color'] . '_' . $row['custom'];
                $value = str_replace(' ', '_', $value); 
                
                $options[] = [
                    'key' => $label,
                    'value' => $value
                ];
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
