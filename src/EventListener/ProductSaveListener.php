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
            $matrixData = $this->variationMatrixService->generateMatrix($object);
            $object->setVariationMatrix(new StructuredTable($matrixData));
        }
    }
}