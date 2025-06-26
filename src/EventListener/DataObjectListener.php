<?php

namespace App\EventListener;

use Exception;
use Pimcore\Model\DataObject\Folder;
use Pimcore\Model\DataObject\Product;

use Pimcore\Event\Model\DataObjectEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class DataObjectListener implements EventSubscriberInterface
{

    /**
     * Returns an array of event names this subscriber wants to listen to.
     *
     * @return array The event names to listen to
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'pimcore.dataobject.preDelete' => 'onPreDelete',
            'pimcore.dataobject.preAdd' => 'onPreAdd',
            'pimcore.dataobject.preUpdate' => 'onPreUpdate',
            'pimcore.dataobject.postUpdate' => 'onPostUpdate',
            'pimcore.dataobject.postLoad' => 'onPostLoad',
        ];
    }

    /**
     * @throws Exception
     */
    public function onPreDelete(DataObjectEvent $event): void
    {
        $object = $event->getObject();
        if ($object instanceof Folder) {
            Product::setGetInheritedValues(false);
            $parent = $object->getParent();
            if ($object->getKey() === 'Ayarlar' || ($parent && $parent->getKey() === 'Ayarlar')) {
                throw new Exception('Ayarlar klasörü ve altındaki ana klasörler silinemez');
            }
        }
        if ($object instanceof Product) {
            if ($object->getDependencies()->getRequiredByTotalCount()) {
                error_log(json_encode($object->getDependencies()->getRequiredBy()));
                throw new Exception('Bu ürün muhtemelen bir setin parçası. Silinemez');
            }
        }
    }


    /**
     * Called before initializing a new object
     * Used to set productCode, variationColor and variationSize values
     * 
     * @param DataObjectEvent $event
     */
    public function onPreAdd(DataObjectEvent $event): void
    {
        $object = $event->getObject();
        if ($object instanceof Product) {
            Product::setGetInheritedValues(false);
            $object->checkProductCode();
        }
    }

    public function onPostUpdate(DataObjectEvent $event): void
    {
        
    }

    /**
     * Called before saving an object to database
     * Used for setting object folder
     * Used for setting Iwasku when set active
     * 
     * $param DataObjectEvent $event
     */
    public function onPreUpdate(DataObjectEvent $event): void
    {
        $object = $event->getObject();
        if ($object instanceof Product && $object->getParent()->getKey() !== 'WISERSELL ERROR') {
            Product::setGetInheritedValues(false);
            $object->checkIwasku();
            $object->checkProductCode();
            $object->checkProductIdentifier();
            $object->checkKey();
        }
    }

}