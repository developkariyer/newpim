<?php

namespace App\Model\AdminStyle;

use Pimcore\Model\DataObject;
use Pimcore\Model\Element\AdminStyle;
use Pimcore\Model\Element\ElementInterface;

class ProductAdminStyle extends AdminStyle
{
    protected ElementInterface $element;

    public function __construct(ElementInterface $element)
    {
        parent::__construct($element);

        $this->element = $element;

        if ($element instanceof \Pimcore\Model\DataObject\Product) {
            DataObject\Service::useInheritedValues(true, function () use ($element) {
                if ($element->getObjectType() == 'actual') {
                    $this->elementIcon = '/bundles/pimcoreadmin/img/flat-color-icons/variant.svg';
                }
            });
        }
    }
}