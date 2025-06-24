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

        \error_log("Listener running for product: " . $object->getKey());

        $newMatrix = $this->variationMatrixService->generateMatrix($object);
        
        \error_log("Generated matrix count: " . count($newMatrix));

        if (!empty($newMatrix)) {
            \error_log("Matrix is NOT empty. Preparing to save.");
            
            // Matrix içeriğini logla
            \error_log("Matrix content: " . print_r($newMatrix, true));
            
            $structuredTable = new StructuredTable();
            $structuredTable->setData($newMatrix);
            
            // setData'dan sonra StructuredTable'ın içeriğini kontrol et
            $retrievedData = $structuredTable->getData();
            \error_log("StructuredTable after setData: " . print_r($retrievedData, true));
            
            $object->setVariationMatrix($structuredTable);
            
            // setVariationMatrix'den sonra Product'tan geri oku
            $checkMatrix = $object->getVariationMatrix();
            if ($checkMatrix) {
                $checkData = $checkMatrix->getData();
                \error_log("Product matrix after set: " . print_r($checkData, true));
            } else {
                \error_log("Product matrix after set: NULL");
            }
            
            $object->save(['disableEvents' => true]);
            \error_log("Save command executed.");
            
            // Save'den sonra tekrar kontrol et
            $finalMatrix = $object->getVariationMatrix();
            if ($finalMatrix) {
                $finalData = $finalMatrix->getData();
                \error_log("Product matrix after save: " . print_r($finalData, true));
            } else {
                \error_log("Product matrix after save: NULL");
            }
            
        } else {
            \error_log("Matrix IS EMPTY. Nothing to save.");
        }
    }
}