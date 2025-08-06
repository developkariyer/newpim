<?php

namespace App\Connector\Marketplace;

use App\Utils\Utility;
use Doctrine\DBAL\Exception;
use Pimcore\Model\DataObject\Data\ExternalImage;
use Pimcore\Model\DataObject\Marketplace;
use Pimcore\Model\Element\DuplicateFullPathException;
use Random\RandomException;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use App\Service\DatabaseService;

class ShopifyConnector  extends MarketplaceConnectorAbstract
{
    public static string $marketplaceType = 'Shopify';

    private string $apiUrl;
    private string $accessToken;
    private $marketplaceKey;
    

    public function __construct($marketplace)
    {
        parent::__construct($marketplace);
        $this->databaseService = \Pimcore::getContainer()->get(DatabaseService::class);
        $this->marketplaceKey = $marketplace->getKey(); 
        $this->apiUrl = trim($_ENV[$this->marketplaceKey . '_API_URL'], characters: "/ \n\r\t");
        $this->accessToken = $_ENV[$this->marketplaceKey . '_ACCESS_TOKEN'] ?? '';
        if (!str_contains($this->apiUrl, 'https://')) {
            $this->apiUrl = "https://{$this->apiUrl}/admin/api/2024-07";
        }
    }

    /**
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     */
    public function getFromShopifyApiGraphql($method, $data, $key = null): ?array
    {
        echo "Getting from Shopify GraphQL\n";
        $allData = [];
        $cursor = null;
        $totalCount = 0;
        $allData[$key] = [];
        do {
            $data['variables']['cursor'] = $cursor;
            $headersToApi = [
                'json' => $data,
                'headers' => [
                    'X-Shopify-Access-Token' => $this->accessToken,
                    'Content-Type' => 'application/json'
                ]
            ];
            while (true) {
                try {
                    $response = $this->httpClient->request($method, $this->apiUrl . '/graphql.json', $headersToApi);
                    $newData = json_decode($response->getContent(), true);
                    echo "Cost Info: " . json_encode($newData['extensions']['cost']) . PHP_EOL;
                    if ($newData['extensions']['cost']['throttleStatus']['currentlyAvailable'] < $newData['extensions']['cost']['actualQueryCost'] ) {
                        $restoreRate =  $this->rateLimitCalculate($newData['extensions']) ?? 5;
                        echo "Rate limit exceeded, waiting for {$restoreRate} seconds..." . PHP_EOL;
                        sleep($restoreRate);
                        continue;
                    }
                    $allData[$key] = array_merge($allData[$key] ?? [], $newData['data'][$key]['nodes'] ?? []);
                    break;
                } catch (\Exception $e) {
                    echo "Request Error: " . $e->getMessage() . PHP_EOL;
                    break;
                }
            }
            $itemsCount = count($newData['data'][$key]['nodes'] ?? []);
            $totalCount += $itemsCount;
            echo "$key Count: $totalCount\n";
            $pageInfo = $newData['data'][$key]['pageInfo'] ?? null;
            $cursor = $pageInfo['endCursor'] ?? null;
            $hasNextPage = $pageInfo['hasNextPage'] ?? false;
        } while ($hasNextPage);
        return $allData;
    }

    public function rateLimitCalculate($extensions): Int
    {
        $actualQueryCost = $extensions['cost']['actualQueryCost'];
        $currentlyAvailable = $extensions['cost']['throttleStatus']['currentlyAvailable'];
        $restoreRate = $extensions['cost']['throttleStatus']['restoreRate'];
        $waitTime = ceil(($actualQueryCost - $currentlyAvailable) / $restoreRate) + 1;
        return max($waitTime, 10);
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     * @throws RandomException
     */
    public function download($forceDownload = false): void
    {
        $getProductQuery = <<<GRAPHQL
            query GetProducts(\$numProducts: Int!, \$cursor: String) {
                products(first: \$numProducts, after: \$cursor) {
                    pageInfo {
                        hasNextPage
                        endCursor
                    }
                    nodes {
                        id
                        title
                        descriptionHtml
                        vendor
                        productType
                        createdAt
                        handle
                        updatedAt
                        publishedAt
                        templateSuffix
                        tags
                        status
                        seo {
                            title
                            description
                        }
                        variantsCount {
                            count
                            precision
                        }
                        variants(first: 200) {
                            pageInfo {
                                hasNextPage
                                endCursor
                            }
                            nodes {
                                id
                                title
                                price
                                position
                                inventoryPolicy
                                compareAtPrice
                                selectedOptions {
                                    name
                                    value
                                }
                                createdAt
                                updatedAt
                                taxable
                                barcode
                                sku
                                inventoryItem {
                                    id
                                }
                                inventoryQuantity
                                image {
                                    id
                                    altText
                                    width
                                    height
                                    src
                                }
                            }
                        }
                        options(first:2) {
                            id
                            name
                            position
                            values
                        }
                        mediaCount {
                            count
                            precision
                        }
                        media (first: 100) {
                            pageInfo {
                                hasNextPage
                                endCursor
                            }
                            nodes {
                                id
                                alt
                                mediaContentType
                                status
                                preview {
                                    image {
                                        id
                                        altText
                                        width
                                        height
                                        url
                                    }
                                }
                            }
                        }
            
                    }
                }
            }
        GRAPHQL;
        echo "GraphQL download\n";
        if ($this->getListingsFromCache()) {
            $this->saveProduct($this->listings);
            echo "Using cached listings\n";
            return;
        }
        $query = [
            'query' => $getProductQuery,
            'variables' => [
                'numProducts' => 50,
                'cursor' => null
            ]
        ];
        $this->listings = $this->getFromShopifyApiGraphql('POST', $query, 'products');
        if (empty($this->listings)) {
            echo "Failed to download listings\n";
            return;
        }
        $this->putListingsToCache();
        $this->saveProduct($this->listings);
        echo "Listings downloaded and saved successfully\n";
    }

    private function saveProduct($listings): void
    {
        if (!isset($listings['products']) || !is_array($listings['products'])) {
            echo "No products data found in listings\n";
            return;
        }
        echo "Found " . count($listings['products']) . " products\n";
        foreach ($listings['products'] as $product) {
            if (!isset($product['variants']['nodes']) || !is_array($product['variants']['nodes'])) {
                echo "No variants found for product: " . ($product['title'] ?? 'unknown') . "\n";
                continue;
            }
            foreach ($product['variants']['nodes'] as $variant) {
                $marketplaceProductUniqueId = basename($variant['id'] ?? '') ?: '';
                $marketplaceSku = $variant['sku']  ?? '';
                $marketplacePrice = $variant['price'] ?? 0;
                $marketplaceCurrency = $this->marketplace->getCurrency();
                $marketplaceCurrency = !empty($marketplaceCurrency) ? $marketplaceCurrency : 'TL';
                $marketplaceStock = $variant['inventoryQuantity'] ?? 0;
                $variantStatus = $variant['status'] ?? $product['status'] ?? 'ACTIVE';
                $status = ($variantStatus === 'ACTIVE') ? 1 : 0;
                $marketplaceProductUrl = $this->marketplace->getMarketplaceUrl() . 'products/' . ($product['handle'] ?? '') . '/?variant=' . basename($variant['id'] ?? '');
                $productData = json_encode([
                    'variant' => $variant,
                    'product_info' => [
                        'title' => $product['title'] ?? '',
                        'handle' => $product['handle'] ?? '',
                        'status' => $product['status'] ?? ''
                    ]
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                $escapedProductData = addslashes($productData);
                $sqlInsertMarketplaceListing = "INSERT INTO iwa_marketplaces_catalog 
                    (marketplace_key, marketplace_product_unique_id, marketplace_sku, marketplace_price, marketplace_currency, marketplace_stock, 
                    status, marketplace_product_url, product_data)
                    VALUES ('$this->marketplaceKey', '$marketplaceProductUniqueId', '$marketplaceSku', '$marketplacePrice', '$marketplaceCurrency',
                    '$marketplaceStock', '$status', '$marketplaceProductUrl', '$escapedProductData')
                    ON DUPLICATE KEY UPDATE
                        marketplace_price = VALUES(marketplace_price),
                        marketplace_currency = VALUES(marketplace_currency),
                        marketplace_stock = VALUES(marketplace_stock),
                        status = VALUES(status),
                        marketplace_product_url = VALUES(marketplace_product_url),
                        product_data = VALUES(product_data)
                    ";
                $this->databaseService->executeSql($sqlInsertMarketplaceListing);
                echo "Inserted listing: " . ($variant['id'] ?? 'unknown') . " - SKU: " . ($variant['barcode'] ?? $variant['sku'] ?? 'no-sku') . "\n";
            }
        }
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     * @throws Exception
     */
    public function downloadOrders(): void
    {
        
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     * @throws RandomException
     */
    public function downloadInventory(): void
    {
       
    }

    /**
     * @throws TransportExceptionInterface
     * @throws RandomException
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     */
    public function downloadReturns(): void
    {
        
    }

    /**
     * @throws DuplicateFullPathException
     * @throws RandomException
     */
    public function import($updateFlag, $importFlag): void
    {
        
    }

    protected function getImage($listing, $mainListing)
    {
        
    }

    /**
     * @throws TransportExceptionInterface
     * @throws RandomException
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     * @throws Exception
     */
    public function setSku(string $sku): void // not tested
    {
        
    }

    /**
     * @throws TransportExceptionInterface
     * @throws RandomException
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     * @throws Exception
     */
    public function setInventory(int $targetValue, $sku = null, $country = null, $locationId = null): void // not tested
    {
        
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     * @throws Exception
     * @throws RandomException
     */
    public function setPrice(string $targetPrice, $targetCurrency = null, $sku = null, $country = null): void // not tested
    {
        
    }

    /**
     * @throws Exception
     * @throws RandomException
     */
    public function setBarcode(string $barcode): void //not tested
    {
        
    }

}