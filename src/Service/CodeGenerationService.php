<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Pimcore\Model\DataObject\Product;
use Pimcore\Model\DataObject\Product\Listing as ProductListing;

class CodeGenerationService
{
    private const UNIQUE_CODE_LENGTH = 5;
    private const CODE_CHARACTERS = 'ABCDEFGHJKMNPQRSTVWXYZ1234567890';
    private const MAX_ATTEMPTS = 1000;

    public function __construct(
        private DataProcessingService $dataProcessor,
        private LoggerInterface $logger
    ) {}

    public function generateProductCode(Product $product): void
    {
        $productCode = $this->generateUniqueCode(self::UNIQUE_CODE_LENGTH);
        $product->setProductCode($productCode);
    }

    public function generateVariantCodes(Product $variant, string $parentIdentifier): void
    {
        if ($variant->getType() === Product::OBJECT_TYPE_VARIANT) {
            $cleanIdentifier = $this->dataProcessor->removeTRChars($parentIdentifier);
            $cleanIdentifier = str_replace(['-', ' '], '', $cleanIdentifier);
            $iwasku = str_pad($cleanIdentifier, 7, '0', STR_PAD_RIGHT);
            $productCode = $this->generateUniqueCode(self::UNIQUE_CODE_LENGTH);
            $variant->setProductCode($productCode);
            $variant->setIwasku($iwasku . $productCode);
        }
    }

    public function generateUniqueCode(int $length = 5): string
    {
        $attempts = 0;
        while ($attempts < self::MAX_ATTEMPTS) {
            $candidateCode = $this->generateRandomString($length);
            
            if (!$this->codeExists($candidateCode)) {
                return $candidateCode;
            }
            
            $attempts++;
        }
        $this->logger->error('Failed to generate unique code', [
            'attempts' => $attempts,
            'length' => $length
        ]);
        throw new \Exception('Maksimum deneme sayısına ulaşıldı (' . self::MAX_ATTEMPTS . '). Benzersiz kod oluşturulamadı.');
    }

    public function generateRandomString(int $length = 5): string
    {
        $characters = self::CODE_CHARACTERS;
        $charactersLength = strlen($characters);
        $result = '';
        for ($i = 0; $i < $length; $i++) {
            $result .= $characters[mt_rand(0, $charactersLength - 1)];
        }
        return $result;
    }

    public function codeExists(string $code): bool
    {
        $listing = new ProductListing();
        $listing->setCondition('productCode = ?', [$code]);
        $listing->setUnpublished(true);
        $listing->setLimit(1);
        $exists = $listing->count() > 0;
        if ($exists) {
            $this->logger->debug('Code already exists', ['code' => $code]);
        }
        return $exists;
    }

    public function generateVariantKey(array $variantData): string
    {
        $parts = array_filter([
            $variantData['renk'] ?? '',
            $variantData['beden'] ?? '',
            $variantData['custom'] ?? ''
        ]);
        return implode('-', $parts) ?: 'variant';
    }
}