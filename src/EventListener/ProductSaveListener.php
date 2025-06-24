<?php

namespace App\EventListener;

use App\Service\VariationMatrixService;
use Pimcore\Event\Model\DataObjectEvent;
use Pimcore\Model\DataObject\Product;
use Pimcore\Model\DataObject\Data\StructuredTable;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: 'pimcore.dataobject.postUpdate')]
#[AsEventListener(event: 'pimcore.dataobject.postAdd')]
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

        $newMatrix = $this->variationMatrixService->generateMatrix($object);
        \error_log("Matrix row count: " . count($newMatrix));
        if (!empty($newMatrix)) {
            $structuredTable = new StructuredTable();
            $structuredTable->setData($newMatrix);
            $object->setVariationMatrix($structuredTable);
            $object->save(['disableEvents' => true]);
        } 
    }
}