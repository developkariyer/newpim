<?php

namespace App\EventListener;

use Pimcore\Event\Model\DataObjectEvent;
use Pimcore\Model\DataObject\VariationColor;
use Pimcore\Model\DataObject\VariationSize;
use Pimcore\Model\DataObject\VariationColorChart;
use Pimcore\Model\DataObject\VariationSizeChart;
use App\Model\DataObject\Product;
use App\Logger\LoggerFactory;

class ProductSaveListener
{
    private $logger;

    public function __construct()
    {
        $this->logger = LoggerFactory::create('Listener', 'ProductSaveListener');
    }

    public function onProductPostSave(DataObjectEvent $event): void
    {
        $product = $event->getObject();
        $this->logger->info('Event tetiklendi!', [
            'product_id' => $product->getId(),
            'product_name' => $product->getKey(),
            'product_class' => get_class($product)
        ]);
        if (!$product instanceof Product) {
            return;
        }
        if ($product->getObjectType() !== 'virtual') {
            return;
        }

        

        //$this->createMissingVariants($product);
    }

    private function createMissingVariants(Product $mainProduct): void
    {
        $sizeOptions = $mainProduct->getSizeOptions();
        $colorOptions = $mainProduct->getColorOptions();

        if (!$sizeOptions || !$colorOptions) {
            $this->logger->warning('Size veya Color options eksik', [
                'product_id' => $mainProduct->getId(),
                'has_size' => $sizeOptions ? 'yes' : 'no',
                'has_color' => $colorOptions ? 'yes' : 'no'
            ]);
            return;
        }

        // SizeOptions ve ColorOptions'dan kombinasyonları al
        $sizes = $this->getSizesFromChart($sizeOptions);
        $colors = $this->getColorsFromChart($colorOptions);

        // Mevcut variant'ları al
        $existingVariants = $this->getExistingVariants($mainProduct);

        $this->logger->info('Variant kontrol bilgileri', [
            'product_id' => $mainProduct->getId(),
            'size_count' => count($sizes),
            'color_count' => count($colors),
            'existing_variants' => count($existingVariants),
            'expected_total' => count($sizes) * count($colors)
        ]);

        // Her size x color kombinasyonu için kontrol et
        foreach ($sizes as $size) {
            foreach ($colors as $color) {
                $variantKey = $this->generateVariantKey($mainProduct->getKey(), $size['name'], $color['name']);
                
                // Bu kombinasyon zaten var mı kontrol et
                if (!$this->variantExists($existingVariants, $variantKey)) {
                    $this->createSingleVariant($mainProduct, $size, $color, $variantKey);
                } else {
                    $this->logger->info('Variant zaten mevcut, atlanıyor', [
                        'variant_key' => $variantKey
                    ]);
                }
            }
        }
    }

    private function getExistingVariants(Product $mainProduct): array
    {
        $variants = [];
        $children = $mainProduct->getChildren();
        
        foreach ($children as $child) {
            if ($child instanceof Product && $child->getObjectType() === Product::OBJECT_TYPE_VIRTUAL) {
                $variants[] = [
                    'object' => $child,
                    'key' => $child->getKey(),
                    'id' => $child->getId()
                ];
            }
        }
        
        return $variants;
    }

    private function variantExists(array $existingVariants, string $variantKey): bool
    {
        foreach ($existingVariants as $variant) {
            if ($variant['key'] === $variantKey) {
                return true;
            }
        }
        return false;
    }

    private function generateVariantKey(string $mainProductKey, string $sizeName, string $colorName): string
    {
        return sprintf('%s %s %s', $mainProductKey, $sizeName, $colorName);
    }

    private function getSizesFromChart(SizeOptions $sizeOptions): array
    {
        $sizes = [];
        
        // SizeOptions sınıfınızın yapısına göre uyarlayın
        if ($sizeOptions->getSize()) {
            $sizes[] = [
                'name' => $sizeOptions->getSize(),
                'width' => $sizeOptions->getWidth(),
                'height' => $sizeOptions->getHeight(),
                'weight' => $sizeOptions->getWeight()
            ];
        }
        
        // Eğer SizeOptions birden fazla size içeriyorsa (örn. Block field)
        // Bu kısmı sınıfınızın yapısına göre güncelleyin
        
        return $sizes;
    }

    private function getColorsFromChart(VariationColor $colorOptions): array
    {
        $colors = [];
        
        // VariationColor sınıfınızın yapısına göre uyarlayın
        if ($colorOptions->getColorName()) {
            $colors[] = [
                'name' => $colorOptions->getColorName(),
                'code' => $colorOptions->getColorCode(),
                'hex' => $colorOptions->getHexValue() ?? null
            ];
        }
        
        return $colors;
    }

    private function createSingleVariant(Product $mainProduct, array $size, array $color, string $variantKey): void
    {
        try {
            $variant = new Product();
            
            // Ana ürünün altına yerleştir
            $variant->setParent($mainProduct);
            $variant->setKey($variantKey);
            $variant->setObjectType(Product::OBJECT_TYPE_VIRTUAL);
            
            // Ana üründen temel bilgileri kopyala (sadece boş olanları)
            $variant->setProductName($variantKey);
            
            // Ana ürün bilgilerini inherit et ama override etme
            if (!$variant->getDescription()) {
                $variant->setDescription($mainProduct->getDescription());
            }
            
            if (!$variant->getPrice()) {
                $variant->setPrice($mainProduct->getPrice());
            }
            
            // Size bilgilerini set et
            $variant->setWidth($size['width'] ?? null);
            $variant->setHeight($size['height'] ?? null);
            $variant->setWeight($size['weight'] ?? null);
            
            // Color bilgilerini set et (Product sınıfınızda color field'ları varsa)
            // $variant->setColorName($color['name']);
            // $variant->setColorCode($color['code']);
            
            // Ana ürüne referans ver (eğer böyle bir field varsa)
            // $variant->setMainProduct($mainProduct);
            
            $variant->setPublished(false); // Varsayılan olarak yayınlanmamış
            $variant->save();
            
            $this->logger->info('Yeni variant oluşturuldu', [
                'variant_id' => $variant->getId(),
                'variant_key' => $variantKey,
                'parent_id' => $mainProduct->getId()
            ]);
            
        } catch (\Exception $e) {
            $this->logger->error('Variant oluşturulurken hata', [
                'error' => $e->getMessage(),
                'variant_key' => $variantKey,
                'main_product_id' => $mainProduct->getId()
            ]);
        }
    }

    /**
     * Eğer size/color chart'ları değişirse, artık kullanılmayan variant'ları 
     * silmek için bu metodu kullanabilirsiniz (isteğe bağlı)
     */
    private function removeObsoleteVariants(Product $mainProduct, array $validCombinations): void
    {
        $existingVariants = $this->getExistingVariants($mainProduct);
        
        foreach ($existingVariants as $variant) {
            if (!in_array($variant['key'], $validCombinations)) {
                $this->logger->info('Artık geçerli olmayan variant siliniyor', [
                    'variant_id' => $variant['id'],
                    'variant_key' => $variant['key']
                ]);
                
                $variant['object']->delete();
            }
        }
    }
}