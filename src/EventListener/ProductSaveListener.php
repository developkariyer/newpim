<?php

namespace App\EventListener;

use App\Service\VariationMatrixService;
use Pimcore\Event\Model\DataObjectEvent;
use Pimcore\Model\DataObject\Product;
use Pimcore\Model\DataObject\Data\StructuredTable;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: 'pimcore.dataobject.preUpdate')]
#[AsEventListener(event: 'pimcore.dataobject.preAdd')]
class ProductSaveListener
{
    private VariationMatrixService $variationMatrixService;

    public function __construct(VariationMatrixService $variationMatrixService)
    {
        $this->variationMatrixService = $variationMatrixService;
    }

    public function __invoke(DataObjectEvent $event): void
    {
        $object = $event->getObject();
        if (!$object instanceof Product) {
            return;
        }
        if ($object->getObjectType() !== 'virtual') {
            return;
        }
        error_log('ProductSaveListener triggered for product: ' . $object->getFullPath());
        $matrixData = $this->variationMatrixService->generateMatrix($object);
        error_log('Matrix data count: ' . count($matrixData));
        $variationMatrix = $object->getVariationMatrix();
        if (!$variationMatrix instanceof StructuredTable) {
            error_log('Creating new StructuredTable for product ' . $object->getId());
            $variationMatrix = new StructuredTable();
            $object->setVariationMatrix($variationMatrix);
        }
        
        if (empty($matrixData)) {
            error_log('Variation matrix data is empty for product ' . $object->getId() . '. Setting empty data.');
            $variationMatrix->setData([]);
            return;
        }
        
    
        $structuredData = [];
        foreach ($matrixData as $index => $row) {
            $structuredData[$index] = [
                'size' => $row['size'],
                'color' => $row['color'], 
                'custom' => $row['custom'],
                'isActive' => $row['isActive'] ? '1' : '0'
            ];
        }
        
        $variationMatrix->setData($structuredData);
        error_log('Set ' . count($structuredData) . ' rows to variation matrix for product ' . $object->getId());
        
        error_log('StructuredTable data: ' . json_encode($variationMatrix->getData()));
    }
}