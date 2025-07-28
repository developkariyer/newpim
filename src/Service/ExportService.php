<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportService
{
    private LoggerInterface $logger;
    private SearchService $searchService;

    private const EXPORT_CHUNK_SIZE = 50; 

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
        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
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
        $this->logger->info('Starting chunked product export to CSV.', [
            'total_limit' => $limit,
            'chunk_size' => self::EXPORT_CHUNK_SIZE,
            'category' => $categoryFilter,
            'search' => $searchQuery,
        ]);
        
        $filename = $this->generateExcelFilename($categoryFilter, $searchQuery);
        $response = new StreamedResponse();
        $response->setCallback(function() use ($limit, $offset, $categoryFilter, $searchQuery, $iwaskuFilter, $asinFilter, $brandFilter, $eanFilter) {
            set_time_limit(0);
            echo "\xEF\xBB\xBF";
            $output = fopen('php://output', 'w');
            $headers = [
                'Ürün ID', 'Ürün Adı', 'Ürün Tanıtıcı', 'Ürün Kodu', 'Kategori', 'Açıklama',
                'Toplam Varyant', 'Oluşturma Tarihi', 'Güncelleme Tarihi', 'Varyant ID',
                'Varyant Adı', 'EAN Kodları', 'ASIN Kodları', 'FNSKU Kodları', 'IWASKU',
                'Varyant Kodu', 'Beden', 'Renk', 'Custom Alan', 'Varyant Durumu', 'Varyant Oluşturma'
            ];
            fputcsv($output, $headers, ';');
            $totalExportedRows = 0;
            $totalChunks = 0;
            for ($currentOffset = $offset; $currentOffset < $limit; $currentOffset += self::EXPORT_CHUNK_SIZE) {
                $this->logger->info("Fetching chunk", ['offset' => $currentOffset]);
                $result = $this->searchService->getFilteredProducts(
                    self::EXPORT_CHUNK_SIZE,
                    $currentOffset,
                    $categoryFilter, $searchQuery, $iwaskuFilter,
                    $asinFilter, $brandFilter, $eanFilter
                );
                $productsChunk = $result['products'] ?? [];
                $chunkCount = count($productsChunk);
                $totalChunks++;
                $this->logger->info("Chunk fetched", [
                    'offset' => $currentOffset,
                    'count' => $chunkCount
                ]);
                if (empty($productsChunk)) {
                    $this->logger->warning("No more products found at offset {$currentOffset}");
                    break;
                }
                foreach ($productsChunk as $product) {
                    $productId = $product['id'] ?? 'N/A';
                    if (empty($product['variants']) || !is_array($product['variants'])) {
                        $row = $this->formatProductRowWithoutVariants($product);
                        fputcsv($output, $row, ';');
                        $totalExportedRows++;
                        continue;
                    }
                    foreach ($product['variants'] as $index => $variant) {
                        if (!is_array($variant)) {
                            $this->logger->warning("Skipping invalid variant", [
                                'product_id' => $productId,
                                'variant_index' => $index
                            ]);
                            continue;
                        }
                        $row = $this->formatProductRowWithVariant($product, $variant, $index);
                        fputcsv($output, $row, ';');
                        $totalExportedRows++;
                    }
                }
            }
            fclose($output);
            $this->logger->info("Export completed", [
                'total_chunks' => $totalChunks,
                'total_rows_exported' => $totalExportedRows
            ]);
        });
        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
        $response->headers->set('Cache-Control', 'max-age=0');
        return $response;
    }

    private function generateCsvOutput(array $products): void
    {
        echo "\xEF\xBB\xBF";
        $output = fopen('php://output', 'w');
        $headers = [
            'Ürün ID', 'Ürün Adı', 'Ürün Tanıtıcı', 'Ürün Kodu', 'Kategori', 'Açıklama',
            'Toplam Varyant', 'Oluşturma Tarihi', 'Güncelleme Tarihi', 'Varyant ID',
            'Varyant Adı', 'EAN Kodları', 'ASIN Kodları', 'FNSKU Kodları', 'IWASKU',
            'Varyant Kodu', 'Beden', 'Renk', 'Custom Alan', 'Varyant Durumu', 'Varyant Oluşturma'
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
            $product['id'],
            $product['name'],
            $product['productIdentifier'],
            $product['productCode'],
            $product['category'] ? $product['category']['displayName'] : '',
            $product['description'],
            0,
            $product['createdAt'] ?? '',
            $product['modifiedAt'] ?? '',
            '', '', '', '', '', '', '', '', '', '', ''
        ];
    }
    
    private function formatProductRowWithVariant(array $product, array $variant, int $index): array
    {
        $eansString = '';
        if (isset($variant['eans']) && is_array($variant['eans']) && !empty($variant['eans'])) {
            $eansString = implode(', ', $variant['eans']);
        }
        $asinsString = '';
        if (isset($variant['asins']) && is_array($variant['asins']) && !empty($variant['asins'])) {
            $asinCodes = array_map(function($asinObj) {
                return $asinObj['asin'] ?? '';
            }, $variant['asins']);
            $asinCodes = array_filter($asinCodes);
            $asinsString = implode(', ', $asinCodes);
        }
        $fnskusString = '';
        if (isset($variant['asins']) && is_array($variant['asins']) && !empty($variant['asins'])) {
            $allFnskus = [];
            foreach ($variant['asins'] as $asinObj) {
                if (isset($asinObj['fnskus']) && is_array($asinObj['fnskus']) && !empty($asinObj['fnskus'])) {
                    $allFnskus = array_merge($allFnskus, $asinObj['fnskus']);
                }
            }
            $allFnskus = array_filter($allFnskus);
            $fnskusString = implode(', ', $allFnskus);
        }
        return [
            $index === 0 ? $product['id'] : '',
            $index === 0 ? $product['name'] : '',
            $index === 0 ? $product['productIdentifier'] : '',
            $index === 0 ? $product['productCode'] : '',
            $index === 0 ? ($product['category'] ? $product['category']['displayName'] : '') : '',
            $index === 0 ? $product['description'] : '',
            $index === 0 ? count($product['variants']) : '',
            $index === 0 ? ($product['createdAt'] ?? '') : '',
            $index === 0 ? ($product['modifiedAt'] ?? '') : '',
            $variant['id'],
            $variant['name'],
            $eansString,
            $asinsString,
            $fnskusString,
            $variant['iwasku'] ?? '',
            $variant['productCode'] ?? '',
            $variant['variationSize'] ?? '',
            $variant['color'] ? ($variant['color']['name'] ?? '') : '',
            $variant['customField'] ?? '',
            isset($variant['published']) ? ($variant['published'] ? 'Aktif' : 'Pasif') : '',
            $variant['createdAt'] ?? ''
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