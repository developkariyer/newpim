<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Pimcore\Model\DataObject\Product;
use Pimcore\Model\DataObject\Color;
use Pimcore\Model\DataObject\Color\Listing as ColorListing;
use App\Service\CodeGenerationService;

class VariantService
{
    public function __construct(
        private LoggerInterface $logger,
        private CodeGenerationService $codeGenerator
    ) {}

    public function createProductVariants(Product $parentProduct, array $variations): void
    {
        foreach ($variations as $variantData) {
            $this->createSingleVariant($parentProduct, $variantData);
        }
    }

    public function createSingleVariant(Product $parentProduct, array $variantData): void
    {
        if (isset($variantData['color']) && !isset($variantData['renk'])) {
            $variantData['renk'] = $variantData['color'];
        }
        if (isset($variantData['size']) && !isset($variantData['beden'])) {
            $variantData['beden'] = $variantData['size'];
        }
        $existingVariant = $this->findVariantByData($parentProduct->getId(), $variantData);
        if ($existingVariant) {
            if (!$existingVariant->getPublished()) {
                $existingVariant->setPublished(true);
                $existingVariant->save();
            }
            return;
        }
        $variant = new Product();
        $variant->setParent($parentProduct);
        $variant->setType(Product::OBJECT_TYPE_VARIANT);
        $variantKey = $this->codeGenerator->generateVariantKey($variantData);
        $fullKey = $parentProduct->getProductIdentifier() . '-' . $parentProduct->getName() . '-' . $variantKey;
        $variant->setKey($fullKey);
        $variant->setName($fullKey);
        $this->setVariantProperties($variant, $variantData);
        $this->codeGenerator->generateVariantCodes($variant, $parentProduct->getProductIdentifier());
        $variant->setPublished(true);
        $variant->save();
    }

    public function setVariantProperties(Product $variant, array $variantData): void
    {
        if (!empty($variantData['renk'])) {
            $color = $this->findColorByName($variantData['renk']);
            if ($color) {
                $variant->setVariationColor($color);
            }
        }
        if (!empty($variantData['beden'])) {
            $variant->setVariationSize($variantData['beden']);
        }
        
        if (!empty($variantData['custom'])) {
            $variant->setCustomField($variantData['custom']);
        }
    }

    public function findVariantByData(int $productId, array $variantData): ?Product
    {
        $product = Product::getById($productId);
        if (!$product) {
            return null;
        }
        $variants = $product->getChildren([Product::OBJECT_TYPE_VARIANT], true);
        foreach ($variants as $variant) {
            if ($this->variantMatches($variant, $variantData)) {
                return $variant;
            }
        }
        return null;
    }

    public function variantMatches(Product $variant, array $variantData): bool
    {
        $variantColor = $variant->getVariationColor() ? $variant->getVariationColor()->getColor() : null;
        $variantSize = $variant->getVariationSize() ?: null;
        $variantCustom = $variant->getCustomField() ?: null;
        $dataColor = $variantData['renk'] ?? $variantData['color'] ?? null;
        $dataSize = $variantData['beden'] ?? $variantData['size'] ?? null;
        $dataCustom = $variantData['custom'] ?? null;
        return $variantColor === $dataColor &&
               $variantSize === $dataSize &&
               $variantCustom === $dataCustom;
    }

    public function createColor(string $colorName, int $colorsFolderId): Color
    {
        $color = new Color();
        $color->setKey($colorName);
        $color->setParentId($colorsFolderId);
        $color->setColor($colorName);
        $color->setPublished(true);
        $color->save();
        return $color;
    }

    public function colorExists(string $colorName): bool
    {
        $listing = new ColorListing();
        $listing->setCondition('color = ?', [$colorName]);
        return $listing->count() > 0;
    }

    public function findColorByName(string $colorName): ?Color
    {
        $listing = new ColorListing();
        $listing->setCondition('color = ?', [$colorName]);
        $listing->setLimit(1);
        return $listing->current();
    }

}