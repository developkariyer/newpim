<?php

namespace App\EventListener;

use Pimcore\Event\Model\DataObjectEvent;
use Pimcore\Event\DataObjectEvents;
use Pimcore\Model\DataObject\CustomChart;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Pimcore\Model\DataObject\ClassDefinition;
use Pimcore\Model\DataObject\Data\Table;

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
        $customOptionsValue = $object->getCustomOptions();
        $data = []; 
        if ($customOptionsValue instanceof Table) {
            $data = $customOptionsValue->getData();
        } elseif (is_array($customOptionsValue)) {
            $data = $customOptionsValue;
        }
        
        $rowCount = count($data);
        if (empty($data)) {
            $objectKey = $object->getKey();
            $newRow = [
                $objectKey
            ];
            $data[] = $newRow;
            $object->setCustomOptions($data);
            try {
                $object->save([
                    'versionNote' => 'System addded column name first row.',
                    'disableEvents' => true
                ]);
            } catch (\Exception $e) {
                error_log('--- Error: ' . $e->getMessage() . ' ---');
            }
        }
    }
}