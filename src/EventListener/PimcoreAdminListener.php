<?php


namespace App\EventListener;

use Pimcore\Bundle\AdminBundle\Event\ElementAdminStyleEvent;
use Pimcore\Event\BundleManager\PathsEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

class PimcoreAdminListener
{
    #[AsEventListener(event: ElementAdminStyleEvent::class, method: 'onResolveElementAdminStyle')] 
    public function onResolveElementAdminStyle(ElementAdminStyleEvent $event): void
    {
        $element = $event->getElement();
        if ($element instanceof \App\Model\DataObject\Product) {
            $event->setAdminStyle(new \App\Model\AdminStyle\ProductAdminStyle($element));
        }
    }
}