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
        error_log('=== CustomOptionsTableListener: Başladı ===');
        $object = $event->getObject();
        if (!$object) {
            error_log('!!! HATA: event->getObject() bir nesne döndürmedi!');
            return;
        }
        $className = get_class($object);
        error_log('>>> Gelen nesne sınıfı: ' . $className);
        if (!$object instanceof CustomChart) {
            error_log('--- Nesne bir CustomChart DEĞİL. İşlem durduruldu. ---');
            return;
        }
        error_log('+++ Nesne bir CustomChart! İşleme devam ediliyor... +++');
    }
}