<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportService
{
    private LoggerInterface $logger;
    private SearchService $searchService;

    public function __construct(LoggerInterface $logger, SearchService $searchService)
    {
        $this->logger = $logger;
        $this->searchService = $searchService;
    }

    public function exportProductsToCsv(
        array $products, 
        ?string $categoryFilter = null, 
        ?string $searchQuery = null
    ): StreamedResponse
    {
        $filename = $this->generateExcelFilename($categoryFilter, $searchQuery);
        $response = new StreamedResponse();
        $response->setCallback(function() use ($products) {
            $this->generateCsvOutput($products);
        });
        $response->headers->set('Content-Type', 'application/vnd.ms-excel; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
        $response->headers->set('Cache-Control', 'max-age=0');
        return $response;
    }

    public function exportFilteredProductsToCsv(
        int $limit, 
        int $offset = 0, 
        ?string $categoryFilter = null, 
        string $searchQuery = '', 
        ?string $iwaskuFilter = null,
        ?string $asinFilter = null, 
        ?string $brandFilter = null, 
        ?string $eanFilter = null
    ): StreamedResponse
    {
        $this->logger->info('Exporting filtered products to CSV', [
            'limit' => $limit,
            'offset' => $offset,
            'category' => $categoryFilter,
            'search' => $searchQuery,
            'iwasku' => $iwaskuFilter,
            'asin' => $asinFilter,
            'brand' => $brandFilter,
            'ean' => $eanFilter
        ]);
        $result = $this->searchService->getFilteredProducts(
            $limit, 
            $offset, 
            $categoryFilter, 
            $searchQuery,
            $iwaskuFilter,
            $asinFilter,
            $brandFilter,
            $eanFilter
        );
        return $this->exportProductsToCsv(
            $result['products'],
            $categoryFilter,
            $searchQuery
        );
    }

    private function generateCsvOutput(array $products): void
    {
        echo "\xEF\xBB\xBF";
        $output = fopen('php://output', 'w');
        $headers = [
            'Ürün ID',
            'Ürün Adı',
            'Ürün Tanıtıcı',
            'Ürün Kodu',
            'Kategori',
            'Açıklama',
            'Toplam Varyant',
            'Oluşturma Tarihi',
            'Güncelleme Tarihi',
            'Varyant ID',
            'Varyant Adı',
            'EAN Kodları', 
            'IWASKU',
            'Varyant Kodu',
            'Beden',
            'Renk',
            'Custom Alan',
            'Varyant Durumu',
            'Varyant Oluşturma'
        ];
        fputcsv($output, $headers, ';');
        foreach ($products as $product) {
            if (empty($product['variants'])) {
                $row = $this->formatProductRowWithoutVariants($product);
                fputcsv($output, $row, ';');
            } else {
                foreach ($product['variants'] as $index => $variant) {
                    $row = $this->formatProductRowWithVariant($product, $variant, $index);
                    fputcsv($output, $row, ';');
                }
            }
        }
        fclose($output);
    }

    private function formatProductRowWithoutVariants(array $product): array
    {
        return [
            $product['id'],                                         // Ürün ID
            $product['name'],                                       // Ürün Adı
            $product['productIdentifier'],                          // Ürün Tanıtıcı
            $product['productCode'],                               // Ürün Kodu
            $product['category'] ? $product['category']['displayName'] : '',  // Kategori
            $product['description'],                                // Açıklama
            0,                                                      // Toplam Varyant
            $product['createdAt'] ?? '',                            // Oluşturma Tarihi
            $product['modifiedAt'] ?? '',                           // Güncelleme Tarihi
            '', '', '', '', '', '', '', '', '', ''                   // Varyant bilgileri (boş)
        ];
    }

    /**
     * Varyantlı ürün satırını formatlar
     */
    private function formatProductRowWithVariant(array $product, array $variant, int $index): array
    {
        $eansString = '';
        if (isset($variant['eans']) && is_array($variant['eans']) && !empty($variant['eans'])) {
            $eansString = implode(', ', $variant['eans']);
        }
        return [
            $index === 0 ? $product['id'] : '',                      // Ürün ID (sadece ilk satırda)
            $index === 0 ? $product['name'] : '',                    // Ürün Adı (sadece ilk satırda)
            $index === 0 ? $product['productIdentifier'] : '',       // Ürün Tanıtıcı (sadece ilk satırda)
            $index === 0 ? $product['productCode'] : '',             // Ürün Kodu (sadece ilk satırda)
            $index === 0 ? ($product['category'] ? $product['category']['displayName'] : '') : '',  // Kategori (sadece ilk satırda)
            $index === 0 ? $product['description'] : '',             // Açıklama (sadece ilk satırda)
            $index === 0 ? count($product['variants']) : '',         // Toplam Varyant (sadece ilk satırda)
            $index === 0 ? ($product['createdAt'] ?? '') : '',       // Oluşturma Tarihi (sadece ilk satırda)
            $index === 0 ? ($product['modifiedAt'] ?? '') : '',      // Güncelleme Tarihi (sadece ilk satırda)
            $variant['id'],                                          // Varyant ID
            $variant['name'],                                        // Varyant Adı
            $eansString,                                             // EAN Kodları
            $variant['iwasku'] ?? '',                                // IWASKU
            $variant['productCode'] ?? '',                           // Varyant Kodu
            $variant['variationSize'] ?? '',                         // Beden
            $variant['color'] ? ($variant['color']['name'] ?? '') : '', // Renk
            $variant['customField'] ?? '',                           // Custom Alan
            isset($variant['published']) ? ($variant['published'] ? 'Aktif' : 'Pasif') : '', // Varyant Durumu
            $variant['createdAt'] ?? ''                              // Varyant Oluşturma
        ];
    }

    private function generateExcelFilename(?string $categoryFilter, ?string $searchQuery): string
    {
        $timestamp = date('Y-m-d_H-i-s');
        $filename = 'urun_katalogu_' . $timestamp;
        if (!empty($categoryFilter)) {
            $filename .= '_kategori_' . $this->sanitizeFilename($categoryFilter);
        }
        if (!empty($searchQuery)) {
            $filename .= '_arama_' . $this->sanitizeFilename($searchQuery);
        }
        return $filename . '.csv';
    }

    private function sanitizeFilename(string $input): string
    {
        $input = str_replace(
            ['ı', 'ğ', 'ü', 'ş', 'ö', 'ç', 'İ', 'Ğ', 'Ü', 'Ş', 'Ö', 'Ç'],
            ['i', 'g', 'u', 's', 'o', 'c', 'I', 'G', 'U', 'S', 'O', 'C'],
            $input
        );
        $input = preg_replace('/[^a-zA-Z0-9_-]/', '_', $input);
        $input = preg_replace('/_+/', '_', $input);
        $input = trim($input, '_');
        return substr($input, 0, 50);
    }
}