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
        
        if (!$object instanceof CustomChart) {
            return;
        }

        $objectName = $object->getKey(); 
        $customOptionsTable = $object->getCustomOptions();
        if (!$customOptionsTable) {
            return; 
        }
        if (is_array($customOptionsTable)) {
            foreach ($customOptionsTable as &$row) {
                if (is_array($row) && isset($row[0])) {
                    $row[0] = $objectName;
                }
            }
            $object->setCustomOptions($customOptionsTable);
            $object->save();
        }
    }
}