<?php


namespace App\Model\DataObject;


class Product extends \Pimcore\Model\DataObject\Product
{
    const OBJECT_TYPE_ACTUAL = 'actual';
    const OBJECT_TYPE_VIRTUAL = 'virtual';


    public function getOSIndexType(): string
    {
        return $this->getObjectType() === self::OBJECT_TYPE_ACTUAL ? self::OBJECT_TYPE_VARIANT : self::OBJECT_TYPE_OBJECT;
    }

}