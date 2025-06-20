<?php


namespace App\EventListener;

use Pimcore\Bundle\AdminBundle\Event\ElementAdminStyleEvent;
use Pimcore\Event\BundleManager\PathsEvent;
use Psr\Log\LoggerInterface;

class PimcoreAdminListener
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function onResolveElementAdminStyle(ElementAdminStyleEvent $event): void
    {
        $element = $event->getElement();
        if ($element instanceof \Pimcore\Model\DataObject\Product) {
            $event->setAdminStyle(new \App\Model\AdminStyle\ProductAdminStyle($element));
        }
    }
}