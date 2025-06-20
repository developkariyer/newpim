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
        $this->logger->info('Listener tetiklendi!');
        $element = $event->getElement();
         $this->logger->info('Element tipi: ' . get_class($element));
        if ($element instanceof \App\Model\DataObject\Product) {
            $this->logger->info('Product bulundu!');
            $this->logger->info('ObjectType: ' . $element->getObjectType());
            $event->setAdminStyle(new \App\Model\AdminStyle\ProductAdminStyle($element));
        }
    }
}