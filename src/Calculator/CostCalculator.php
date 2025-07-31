<?php

namespace App\Calculator;

use Pimcore\Model\DataObject\Concrete;
use Pimcore\Model\DataObject\ClassDefinition\CalculatorClassInterface;
use Pimcore\Model\DataObject\Data\CalculatedValue;
use Pimcore\Model\DataObject\Product;

class CostCalculator implements CalculatorClassInterface
{
    public function compute(Concrete $object, CalculatedValue $context): string
    {
        if (!($object instanceof Product) || $object->getType() !== \Pimcore\Model\DataObject::OBJECT_TYPE_VARIANT) {
            return '';
        }
        return match ($context->getFieldname()) {
            'productCost' => $this->calculateProductCost($object),
            default => '',
        };
    }

    private function calculateProductCost(Product $object): string
    {
        $totalCost = '0.00';
        $bundleItems = $object->getBundleProducts();
        echo 'Calculating cost for product: ' . $object->getIwasku() . PHP_EOL;
        echo 'Bundle items count: ' . count($bundleItems) . PHP_EOL;
        if (!empty($bundleItems)) {
            foreach ($bundleItems as $index => $bundleItem) {
                echo 'Processing bundle item ' . ($index + 1) . PHP_EOL;
                $product = $bundleItem->getObject();
                if ($product === null) {
                    echo 'ERROR: Bundle item ' . ($index + 1) . ' has null object' . PHP_EOL;
                    continue;
                }
                echo 'Bundle item product iwasku: ' . $product->getIwasku() . PHP_EOL;
                $bundleItemCost = $product->getProductCost() ?? '0.00';
                echo 'Bundle item cost: ' . $bundleItemCost . PHP_EOL;
                $totalCost = bcadd($totalCost, (string)$bundleItemCost, 4);
            }
            echo 'Final bundle cost: ' . $totalCost . PHP_EOL;
            return number_format($totalCost, 4, '.', '');
        }
    }
    public function getCalculatedValueForEditMode(Concrete $object, CalculatedValue $context): string
    {
        return $this->compute($object, $context);
    }

}