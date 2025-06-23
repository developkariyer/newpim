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
        $classDefinition = ClassDefinition::getByName('CustomChart');
        if (!$classDefinition) {
            return;
        }
        $customOptionsField = $classDefinition->getFieldDefinition('customOptions');
        if (!$customOptionsField instanceof Table) {
            return;
        }
        $columns = $customOptionsField->getCols();
        if ($columns && isset($columns[0])) {
            $columns[0]['label'] = $objectName;
            $customOptionsField->setCols($columns);
            $classDefinition->save();
        }
    }
}