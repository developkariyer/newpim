<?php

namespace App\Model\DataObject;

use Exception;
use Pimcore\Model\Asset;
use Pimcore\Model\Asset\Folder;
use Pimcore\Model\DataObject\AbstractObject;
use Pimcore\Model\DataObject\Concrete;
use Pimcore\Model\DataObject\Product\Listing;
use Pimcore\Model\DataObject\Service;
use Pimcore\Model\Element\DuplicateFullPathException;


class Product extends Concrete
{
    const OBJECT_TYPE_ACTUAL = 'actual';
    const OBJECT_TYPE_VIRTUAL = 'virtual';

    public function checkProductCode($numberDigits = 5): bool
    {
        Product::setGetInheritedValues(false);
        if (strlen($this->getProductCode()) == $numberDigits) {
            Product::setGetInheritedValues(true);
            return false;
        }
        $productCode = $this->generateUniqueCode($numberDigits);
        $this->setProductCode($productCode);
        Product::setGetInheritedValues(true);
        return true;
    }

    public function checkIwasku(bool $forced = false): bool
    {
        if ($forced || ($this->getObjectType() === self::OBJECT_TYPE_VIRTUAL && $this->isPublished() && strlen($this->getIwasku() ?? '') != 12)) {
            $pid = $this->getInheritedField("productIdentifier");
            $iwasku = str_pad(str_replace('-', '', $pid), 7, '0', STR_PAD_RIGHT);
            $productCode = $this->getProductCode();
            if (strlen($productCode) != 5) {
                $productCode = $this->generateUniqueCode(5);
                $this->setProductCode($productCode);
            }
            $iwasku .= $productCode;
            $this->setIwasku($iwasku);
            return true;
        }
        return false;
    }

    public function checkKey(): void
    {
        $key = $this->getInheritedField("ProductIdentifier");
        $key .= " ";
        $key .= $this->getInheritedField("Name");
        $variationSize = $this->getInheritedField("VariationSize");
        $variationColor = $this->getInheritedField("VariationColor");
        if (!empty($variationSize)) {
            $key .= " $variationSize";
        }
        if (!empty($variationColor)) {
            $key .= " $variationColor";
        }
        if (!empty($key)) {
            $this->setKey($key);
        } else {
            $this->setKey("gecici_{$this->generateUniqueCode(10)}");
        }
    }

    public function checkProductIdentifier(): void
    {
        if (empty($this->getProductIdentifier())) {
            return;
        }
        $productIdentifier = $this->getProductIdentifier();
        if (preg_match('/^([A-Z]{2,3}-)(\d+)([A-Z]?)$/', $productIdentifier, $matches)) {
            $paddedNumber = str_pad($matches[2], 3, '0', STR_PAD_LEFT);
            $this->setProductIdentifier($matches[1] . $paddedNumber . $matches[3]);
        }
    }

    public function generateUniqueCode(int $numberDigits=5): string
    {
        while (true) {
            $candidateCode = self::generateCustomString($numberDigits);
            if (!$this->findByField('productCode', $candidateCode)) {
                return $candidateCode;
            }
        }
    }

    public static function generateCustomString(int $length = 5): string
    {
        $characters = 'ABCDEFGHJKMNPQRSTVWXYZ1234567890';
        $charactersLength = strlen($characters);
        $randomString = '';

        for ($i = 0; $i < $length; $i++) {
            $randomIndex = mt_rand(0, $charactersLength - 1);
            $randomString .= $characters[$randomIndex];
        }
        return $randomString;
    }

    public function getInheritedField(string $field): mixed
    {
        return Service::useInheritedValues(true, function() use ($field) {
            $object = $this;
            $fieldName = "get" . ucfirst($field);
            return $object->$fieldName();
        });
    }
    
}