<?php

namespace App\EventListener;

use App\Service\VariationMatrixService;
use Pimcore\Event\Model\DataObjectEvent;
use Pimcore\Model\DataObject\Product;
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

        try {
            $newMatrix = $this->variationMatrixService->generateMatrix($object);
            if (!empty($newMatrix)) {
                $object->setVariationMatrix($newMatrix);
                $object->save();
            } 
            
        } catch (\Exception $e) {
            \Pimcore\Log\Simple::log('product-save', "Error in ProductSaveListener: " . $e->getMessage());
        }
    }
}