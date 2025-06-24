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
        if ($object instanceof Product) {
            error_log('ProductSaveListener triggered for product: ' . $object->getFullPath());
            $matrixData = $this->variationMatrixService->generateMatrix($object);

            if (empty($matrixData)) {
                error_log('Variation matrix data is empty for product ' . $object->getId() . '. The table will be empty.');
            } else {
                error_log('Generated variation matrix data for product ' . $object->getId()
                . json_encode($matrixData));
            }

            $object->setVariationMatrix(new StructuredTable($matrixData));
        }
    }
}