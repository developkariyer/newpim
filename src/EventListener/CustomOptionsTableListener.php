<?php

namespace App\EventListener;

use Pimcore\Event\Model\DataObjectEvent;
use Pimcore\Event\DataObjectEvents;
use Pimcore\Model\DataObject\CustomChart;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Pimcore\Model\DataObject\ClassDefinition;
use Pimcore\Model\DataObject\ClassDefinition\Data\Table;

class CustomOptionsTableListener implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            DataObjectEvents::POST_ADD => 'onDataObjectPostAdd',
        ];
    }

    public function onDataObjectPostAdd(DataObjectEvent $event): void
    {
        $object = $event->getObject();
        if (!$object instanceof DataObject\CustomChart) {
            return;
        }
        $tableField = $object->getCustomOptions();
        $data = $tableField ? $tableField->getData() : [];
        if (empty($data)) {
            $newRow = [
                $object->getKey(), 
                '',                
                '',                
            ];
            $data[] = $newRow;
            $object->setCustomOptions($data);
            $object->save([
                'versionNote' => 'Automatically added initial chart data.',
                'disableEvents' => true 
            ]);
        }
    }
}