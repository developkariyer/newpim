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
        error_log('=== Adım 1 & 2: Başarılı. Listener çalışıyor ve nesne bir CustomChart. ===');
        $object = $event->getObject();
        error_log('>>> Adım 3.1: "customOptions" alanı alınıyor...');
        $customOptionsValue = $object->getCustomOptions();
        $data = []; 
        if ($customOptionsValue instanceof Table) {
            $data = $customOptionsValue->getData();
            error_log('>>> "customOptions" bir Table nesnesi olarak geldi. Veri alındı.');
        } elseif (is_array($customOptionsValue)) {
            $data = $customOptionsValue;
            error_log('>>> "customOptions" bir DİZİ olarak geldi. Doğrudan kullanılıyor.');
        }
        
        $rowCount = count($data);
        error_log(">>> Mevcut satır sayısı: " . $rowCount);
        if (empty($data)) {
            error_log('+++ Adım 3.2: Tablo boş. Yeni satır ekleme işlemi başlıyor...');
            $objectKey = $object->getKey();
            error_log(">>> Eklenecek anahtar (nesne adı): " . $objectKey);
            $newRow = [
                $objectKey
            ];
            
            $data[] = $newRow;
            error_log(">>> Yeni satır veri dizisine eklendi.");
            $object->setCustomOptions($data);
            error_log(">>> Güncel veri nesneye set edildi (setCustomOptions).");
            error_log(">>> Adım 3.3: Nesne kaydediliyor...");
            try {
                $object->save([
                    'versionNote' => 'Sistem tarafından ilk tablo satırı eklendi.',
                    'disableEvents' => true
                ]);
                error_log('*** BAŞARILI! Nesne yeni tablo verisiyle kaydedildi. ***');
            } catch (\Exception $e) {
                error_log('!!! HATA: Nesne kaydedilirken bir sorun oluştu: ' . $e->getMessage());
            }
        } else {
            error_log('--- Adım 3.2: Tablo zaten dolu. Herhangi bir işlem yapılmadı. ---');
        }
        error_log('=== CustomOptionsTableListener: Tamamlandı ===');
    }
}