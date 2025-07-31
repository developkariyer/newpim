<?php

namespace App\Command;

use Carbon\Carbon;
use Pimcore\Console\AbstractCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Pimcore\Model\DataObject\Product;
use App\Service\AssetManagementService;
use Pimcore\Model\DataObject\Folder;
use Pimcore\Model\DataObject\Category;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Pimcore\Model\DataObject\Color;
use Pimcore\Model\DataObject\Ean;
use Pimcore\Model\DataObject\Asin;
use Pimcore\Model\DataObject\Color\Listing as ColorListing;

#[AsCommand(
    name: 'app:import',
    description: 'Import Products!'
)]
class ImportCommand extends AbstractCommand
{
    private const PRODUCTS_MAIN_FOLDER_ID = 1246;

    public function __construct(
        private AssetManagementService $assetService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Import NewPim Sync Data')
            ->addOption('products', null, InputOption::VALUE_NONE, 'Source Marketplace Name')
            ->addOption('eans', null, InputOption::VALUE_NONE, 'Target Marketplace Name')
            ->addOption('asins', null, InputOption::VALUE_NONE, 'Target Marketplace Name')
            ->addOption('connectEan', null, InputOption::VALUE_NONE, 'Connect Product EAN')
            ->addOption('connectAsin', null, InputOption::VALUE_NONE, 'Connect Product ASIN')
            ->addOption('setProduct', null, InputOption::VALUE_NONE, 'Set Product Set');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $data = $this->readDataJsonFile();

        if ($input->getOption('products')) {
            $this->importProducts($data);
        }

        if ($input->getOption('eans')) {
            $this->createEan($data);
        }    
        
        if ($input->getOption('asins')) {
            $this->createAsinFnsku($data);
        }

        if ($input->getOption('connectEan')) {
            $this->connectProductEan($data);
        }

        if ($input->getOption('connectAsin')) {
            $this->connectProductAsin($data);
        }

        if ($input->getOption('setProduct')) {
            $this->setProductSetProduct($data);
        }

        return Command::SUCCESS;
    }

    // set product set
    private function setProductSetProduct($data)
    {
        foreach ($data as $product) {
            foreach ($product['variants'] as $variant) {
                $setProductIwaskus = $variant['setProductIwaskus'] ?? [];
                if (!is_array($setProductIwaskus) || count($setProductIwaskus) === 0) {
                    continue;
                }
                echo "Set product iwaskus for variant: $variantIwasku\n";
                foreach ($setProductIwaskus as $iwasku => $value) {
                    echo "- $iwasku => $value\n";
                }
            }
        }

    }

    private function connectProductEan($data)
    {
        foreach ($data as $product) {
            foreach ($product['variants'] as $variant) {
                $iwasku = $variant['iwasku'] ?? '';
                if (empty($iwasku)) {
                    echo 'Skipping variant with empty iwasku for product ' . $product['identifier'] . PHP_EOL;
                    continue;
                }
                $eanCode = $variant['ean'] ?? '';
                if (empty($eanCode)) {
                    echo 'Skipping variant with empty EAN for iwasku ' . $iwasku . PHP_EOL;
                    continue;
                }
                $ean = $this->findEanByCode($eanCode);
                if (!$ean) {
                    echo 'EAN not found for code ' . $eanCode . ', skipping variant with iwasku ' . $iwasku . PHP_EOL;
                    continue;
                }
                $variantObject = $this->findVariantByIwasku($iwasku);
                if (!$variantObject) {
                    echo 'Variant not found for iwasku ' . $iwasku . ', skipping EAN connection.' . PHP_EOL;
                    continue;
                }
                $ean->setProduct($variantObject);
                $ean->setPublished(true);
                try {
                    $ean->save();
                    echo 'Connected EAN ' . $eanCode . ' to variant with iwasku ' . $iwasku . PHP_EOL;
                } catch (\Exception $e) {
                    echo 'Failed to connect EAN ' . $eanCode . ' to variant with iwasku ' . $iwasku . ': ' . $e->getMessage() . PHP_EOL;
                }
            }
        }
    }

    private function connectProductAsin($data)
    {
        foreach ($data as $product) {
            foreach ($product['variants'] as $variant) {
                $iwasku = $variant['iwasku'] ?? '';
                if (empty($iwasku)) {
                    echo 'Skipping variant with empty iwasku for product ' . $product['identifier'] . PHP_EOL;
                    continue;
                }
                $asinMap = $variant['asinMap'] ?? [];
                if (empty($asinMap)) {
                    echo 'No ASIN map found for product ' . $product['identifier'] . ', skipping variant ' . $iwasku . PHP_EOL;
                    continue;
                }
                $variantObject = $this->findVariantByIwasku($iwasku);
                if (!$variantObject) {
                    echo 'Variant not found for iwasku ' . $iwasku . ', skipping ASIN connection.' . PHP_EOL;
                    continue;
                }
                $currentAsins = $variantObject->getAsin();
                if (!is_array($currentAsins)) {
                    $currentAsins = [];
                }
                $updated = false;
                foreach (array_keys($asinMap) as $asinCode) {
                    if (empty($asinCode)) {
                        echo 'Skipping empty ASIN key for iwasku ' . $iwasku . PHP_EOL;
                        continue;
                    }
                    $asin = $this->findAsinByCode($asinCode);
                    if (!$asin) {
                        echo 'ASIN not found for code ' . $asinCode . ', skipping for iwasku ' . $iwasku . PHP_EOL;
                        continue;
                    }
                    $alreadyExists = false;
                    foreach ($currentAsins as $existingAsin) {
                        if ($existingAsin instanceof \Pimcore\Model\DataObject\Asin && $existingAsin->getId() === $asin->getId()) {
                            $alreadyExists = true;
                            break;
                        }
                    }
                    if ($alreadyExists) {
                        echo 'ASIN ' . $asinCode . ' already connected to variant with iwasku ' . $iwasku . PHP_EOL;
                        continue;
                    }
                    $currentAsins[] = $asin;
                    echo 'Prepared to connect ASIN ' . $asinCode . ' to variant with iwasku ' . $iwasku . PHP_EOL;
                    $updated = true;
                }
                if ($updated) {
                    $variantObject->setAsin($currentAsins);
                    $variantObject->setPublished(true);
                    try {
                        $variantObject->save();
                        echo 'Saved variant ' . $iwasku . ' with new ASINs.' . PHP_EOL;
                    } catch (\Exception $e) {
                        echo 'Failed to save variant ' . $iwasku . ': ' . $e->getMessage() . PHP_EOL;
                    }
                } else {
                    echo 'No ASINs added for iwasku ' . $iwasku . PHP_EOL;
                }
            }
        }
    }

    private function findAsinByCode($asinCode)
    {
        $listing = new Asin\Listing();
        $listing->setCondition('asin = ?', [$asinCode]);
        $listing->setLimit(1);
        $listing->load();
        return $listing->current();
    }

    private function findEanByCode($eanCode)
    {
        $listing = new Ean\Listing();
        $listing->setCondition('GTIN = ?', [$eanCode]);
        $listing->setLimit(1);
        $listing->load();
        return $listing->current();
    }

    private function findVariantByIwasku($iwasku)
    {
        $listing = new Product\Listing();
        $listing->setCondition('iwasku = ?', [$iwasku]);
        $listing->setLimit(1);
        $listing->load();
        return $listing->current();
    }

    private function readDataJsonFile()
    {
        $filePath = PIMCORE_PROJECT_ROOT . '/tmp/exportProduct.json';
        if (!file_exists($filePath)) {
            echo 'File not found: ' . $filePath . PHP_EOL;
            return null;
        }
        $jsonContent = file_get_contents($filePath);
        $data = json_decode($jsonContent, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            echo 'JSON decode error: ' . json_last_error_msg() . PHP_EOL;
            return null;
        }
        if (!is_array($data)) {
            echo 'Decoded JSON is not an array.' . PHP_EOL;
            return null;
        }
        echo count($data) . ' products found in the JSON file.' . PHP_EOL;
        return $data;
    }
    
    private function importProducts($data)
    {
        $summary = [
            'total_products' => count($data),
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'failed' => 0,
            'total_variants' => 0,
            'imported_variants' => 0,
        ];
        foreach ($data as $index => $product) {
            $result = $this->createProduct($product);
            switch ($result['product_status']) {
                case 'created':
                    $summary['created']++;
                    break;
                case 'updated':
                    $summary['updated']++;
                    break;
                case 'skipped':
                    $summary['skipped']++;
                    break;
                case 'failed':
                    $summary['failed']++;
                    break;
            }
            $summary['total_variants'] += $result['variant_total'];
            $summary['imported_variants'] += $result['variant_success'];
        }
        echo PHP_EOL;
        echo 'Summary:' . PHP_EOL;
        echo '---------------' . PHP_EOL;
        echo 'Total Products        : ' . $summary['total_products'] . PHP_EOL;
        echo 'Created               : ' . $summary['created'] . PHP_EOL;
        echo 'Updated               : ' . $summary['updated'] . PHP_EOL;
        echo 'Skipped               : ' . $summary['skipped'] . PHP_EOL;
        echo 'Failed                : ' . $summary['failed'] . PHP_EOL;
        echo 'Total Variants        : ' . $summary['total_variants'] . PHP_EOL;
        echo 'Imported Variants     : ' . $summary['imported_variants'] . PHP_EOL;
        return Command::SUCCESS;
    }

    private function createProduct(array $data)
    {
        $status = [
            'identifier' => $data['identifier'] ?? '',
            'product_status' => null,
            'variant_total' => 0,
            'variant_success' => 0
        ];
        try {
            $isExist = $this->checkExistProduct($data['identifier']);
            if ($isExist) {
                echo 'Product with identifier ' . $data['identifier'] . ' already exists.' . PHP_EOL;
                $this->updateProduct($data);
                $status['product_status'] = 'updated';
                return $status;
            }
            $imageAsset = null;
            if ($data['image']) {
                $data['image'] = 'https://iwa.web.tr' . $data['image'];
                $imageName = $data['identifier'] ?: $data['name'];
                $uploadedFile = $this->createUploadedFileFromUrl($data['image'], $imageName);
                if ($uploadedFile) {
                    $imageAsset = $this->assetService->uploadProductImage(
                        $uploadedFile,
                        $imageName
                    );
                }
            }
            $parentFolder = $this->createProductFolderStructure($data['identifier'], $data['category']);
            $key = $data['identifier'] . ' ' . $data['name'];
            if (empty(trim($key))) {
                echo 'Product key is empty for identifier ' . $data['identifier'] . ', skipping.' . PHP_EOL;
                $status['product_status'] = 'skipped';
                return;
            }

            $product = new Product();
            $product->setParent($parentFolder);
            $product->setKey($key);
            $product->setProductIdentifier($data['identifier']);
            $product->setName($data['name']);
            $product->setDescription($data['description']);
            $product->setProductCategory($this->getProductCategory($data['category']));
            $product->setProductCode($data['productCode']);
            $product->setMarketplaces($this->getMarketplaceObjects($data['variants'] ?? []));
            $product->setVariationSizeTable($this->createSizeTable($data['sizeTable'] ?? []));
            $product->setCustomFieldTable($this->createCustomTable($data['customFieldTable'] ?? []));
            $product->setPublished($data['published'] ?? true);
            if ($imageAsset) {
                $product->setImage($imageAsset);
            }
            $product->save();
            $variants = $data['variants'] ?? [];
            $status['variant_total'] = count($variants);
            $status['variant_success'] = $this->createVariant($product, $variants);
            $status['product_status'] = 'created';
            return $status;
        } catch (\Exception $e) {
            echo 'Error processing product with identifier ' . $data['identifier'] . ': ' . $e->getMessage() . PHP_EOL;
            $status['product_status'] = 'failed';
            return $status;
        }
    }

    private function updateProduct(array $data)
    {
        // $imageAsset = null;
        // if ($data['image']) {
        //     $data['image'] = 'https://iwa.web.tr' . $data['image'];
        //     $imageName = $data['identifier'] ?: $data['name'];
        //     $uploadedFile = $this->createUploadedFileFromUrl($data['image'], $imageName);
        //     if ($uploadedFile instanceof \Symfony\Component\HttpFoundation\File\UploadedFile) {
        //         $imageAsset = $this->assetService->uploadProductImage(
        //             $uploadedFile,
        //             $imageName
        //         );
        //     } else {
        //         echo "Image could not be downloaded or processed for product: " . $data['identifier'] . PHP_EOL;
        //     }
        // }
        // $listing = new Product\Listing();
        // $listing->setCondition('productIdentifier = ?', [$data['identifier']]);
        // $listing->setLimit(1);
        // $listing->load();
        // $product = $listing->current();
        // if (!$product) {
        //     echo 'Product not found for identifier ' . $data['identifier'] . ', skipping update.' . PHP_EOL;
        //     return;
        // }
        // $imageCheck  = $product->getImage();
        // if ($imageCheck) {
        //    return;
        // }
        // if ($imageAsset) {
        //     $product->setImage($imageAsset);
        // }
        // $product->save();
    }

    private function checkExistProduct($productIdentifier)
    {
        $listing = new Product\Listing();
        $listing->setCondition('productIdentifier = ?', [$productIdentifier]);
        $listing->setLimit(1);
        $listing->load();
        return $listing->count() > 0;
    }

    private function createVariant($parentProduct, $data): int
    {
        if (!is_array($data) || empty($data)) {
            return 0;
        }
        $successCount = 0;
        foreach ($data as $variantData) {
            if (empty(trim($variantData['key']))) {
                echo 'Variant key is empty for product ' . $parentProduct->getProductIdentifier() . ', skipping.' . PHP_EOL;
                continue;
            }
            $color = $this->createColor($variantData['variationColor']);
            if (!$color) {
                echo 'Skipping variant due to empty color for product ' . $parentProduct->getProductIdentifier() . PHP_EOL;
                continue;
            }
            try {
                $variant = new Product();
                $variant->setParent($parentProduct);
                $variant->setType(Product::OBJECT_TYPE_VARIANT);
                $variant->setProductCode($variantData['productCode']);
                $variant->setIwasku($variantData['iwasku']);
                $variant->setKey($variantData['key']);
                $variant->setName($variantData['name']);
                $variant->setVariationColor($color);
                $variant->setVariationSize($variantData['variationSize'] ?? '');
                $variant->setCustomField($variantData['customField'] ?? '');
                $variant->setPublished($variantData['published']);
                $variant->save();
                $successCount++;
            } catch (\Exception $e) {
                echo 'Error saving variant for product ' . $parentProduct->getProductIdentifier() . ': ' . $e->getMessage() . PHP_EOL;
            }
        }

        return $successCount;
    }

    private function createColor($variationColor)
    {
        $color = $this->findColorByName($variationColor);
        if (!$color) {
            $color = new Color();
            if (empty(trim($variationColor))) {
                echo 'Color key is empty, skipping.' . PHP_EOL;
                return null;
            }
            $color->setKey($variationColor);
            $color->setParentId(1247);
            $color->setColor($variationColor);
            $color->setPublished(true);
            $color->save();
            return $color;
        }
        return $color;
    }

    public function findColorByName(string $colorName)
    {
        $listing = new ColorListing();
        $listing->setCondition('color = ?', [$colorName]);
        $listing->setLimit(1);
        return $listing->current();
    }

    private function createCustomTable(array $customTable): array
    {
        $result = [];
        foreach ($customTable as $item) {
            if (!empty($item)) {
                $result[] = [
                    'value' => (string)$item,
                ];
            }
        }
        return $result;
    }

    private function createSizeTable($sizeTable): array
    {
        //print_r($sizeTable);
        $result = [];
        foreach ($sizeTable as $row) {
            if (empty($row)) {
                continue; 
            }

            $label = (string)array_pop($row); 
            //echo 'Size Table Row: ' . implode(', ', $row) . PHP_EOL;

            $result[] = [
                'label'  => $label,
                'width'  => (string)($row[0] ?? '0'),
                'length' => (string)($row[1] ?? '0'),
                'height' => (string)($row[2] ?? '0'),
            ];
        }
        return $result;
    }

    private function createAsinFnsku($data)
    {
        $uniqueAsins = [];
        foreach ($data as $product) {
            foreach ($product['variants'] as $variant) {
                if (isset($variant['asinMap']) && is_array($variant['asinMap'])) {
                    foreach ($variant['asinMap'] as $asin => $fnskuList) {
                        if (!in_array($asin, $uniqueAsins)) {
                            $uniqueAsins[] = $asin;
                            $fnskuString = is_array($fnskuList) ? implode("\n", $fnskuList) : (string)$fnskuList;
                            $asinModel = new Asin();
                            $asinModel->setKey($asin);
                            $asinModel->setParentId(54); 
                            $asinModel->setAsin($asin);
                            $asinModel->setFnskus($fnskuString);
                            $asinModel->setPublished(true);
                            $asinModel->save();
                        }
                    }
                }
            }
        }
    }
    
    private function createEan($data)
    {
        $uniqueEans = [];
        foreach ($data as $product) {
            foreach ($product['variants'] as $variant) {
                if (
                    isset($variant['ean']) &&
                    !empty($variant['ean']) && 
                    !in_array($variant['ean'], $uniqueEans)
                ) {
                    $uniqueEans[] = $variant['ean'];
                }
            }
        }
        $uniqueEans = array_unique($uniqueEans);
        foreach ($uniqueEans as $ean) {
            if (empty($ean)) {
                continue;
            }
            $eanModel = new Ean();
            $eanModel->setKey($ean);
            $eanModel->setParentId(47);
            $eanModel->setGTIN($ean);
            $eanModel->setPublished(true);
            $eanModel->save();
        }

    }

    private function createUniqueColor($data)
    {
        $uniqueColors = [];
        foreach ($data as $product) {
            foreach ($product['variants'] as $variant) {
                if (isset($variant['variationColor']) && !in_array($variant['variationColor'], $uniqueColors)) {
                    $uniqueColors[] = $variant['variationColor'];
                }
            }
        }    
        $uniqueColors = array_unique($uniqueColors);
        foreach ($uniqueColors as $colorName) {
            if (empty($colorName)) {
                continue; 
            }
            $existingColor = $this->findColorByName($colorName);
            if ($existingColor) {
                continue;
            }
            
            $cleanKey = strtolower(trim($colorName));
            $cleanKey = str_replace(' ', '-', $cleanKey);
            $cleanKey = preg_replace('/[^a-z0-9\-_]/', '', $cleanKey);
            if (empty($cleanKey)) {
                continue;
            }
            $color = new Color();
            $color->setKey($cleanKey);
            $color->setParentId(1247);
            $color->setColor($colorName);
            $color->setPublished(true);
            $color->save();
        } 
    }

    private function getMarketplaceObjects(array $variants): array
    {
        $marketplaceNames = $this->getMarketplaces($variants);
        $marketplaces = [];
        foreach ($marketplaceNames as $name) {
            $marketplaceListing = new \Pimcore\Model\DataObject\Marketplace\Listing();
            $marketplaceListing->setCondition("`key` = ?", [$name]);
            $marketplaceListing->setLimit(1);
            $marketplaceListing->load();
            if ($marketplaceListing->count() === 0) {
                continue;
            }
            $marketplace = $marketplaceListing->current();
            if ($marketplace) {
                $marketplaces[] = $marketplace;
            }
        }
        return $marketplaces;
    }

    private function getMarketplaces($variants)
    {
        $marketplaces = [];
        foreach ($variants as $variant) {
            if (isset($variant['marketplaceList'])) {
                if (is_array($variant['marketplaceList'])) {
                    foreach ($variant['marketplaceList'] as $mp) {
                        $marketplaces[] = $mp;
                    }
                } else {
                    $marketplaces[] = $variant['marketplaceList'];
                }
            }
        }
        return array_unique($marketplaces);
    }

    private function createUploadedFileFromUrl(string $url, string $name = null): ?UploadedFile
    {
        $parsedUrl = parse_url($url);
        $pathParts = explode('/', $parsedUrl['path']);
        $encodedPath = implode('/', array_map('rawurlencode', $pathParts));
        $encodedUrl = $parsedUrl['scheme'] . '://' . $parsedUrl['host'] . $encodedPath;
        $tmpFile = tempnam(sys_get_temp_dir(), 'upl_');
        $fileContent = @file_get_contents($encodedUrl);
        if ($fileContent === false) {
            echo "Image download failed: $encodedUrl" . PHP_EOL;
            return null;
        }
        file_put_contents($tmpFile, $fileContent);
        $mimeType = mime_content_type($tmpFile);
        if (!in_array($mimeType, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'])) {
            echo "Downloaded file is not a valid image: $encodedUrl" . PHP_EOL;
            return null;
        }
        $ext = match ($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            default => pathinfo($url, PATHINFO_EXTENSION),
        };
        $originalName = $name ?: basename($parsedUrl['path']);
        if (!str_ends_with($originalName, '.' . $ext)) {
            $originalName .= '.' . $ext;
        }
        return new UploadedFile(
            $tmpFile,
            $originalName,
            $mimeType,
            null,
            true
        );
    }

    private function getProductCategory(string $categoryName)
    {
        $categoryListing = new Category\Listing();
        $categoryListing->setCondition("category = '$categoryName'");
        $categoryListing->setLimit(1);
        $categoryListing->load();
        if ($categoryListing->count() === 0) {
            return null;
        }
        $category = $categoryListing->current();
        return $category ?: null;
        
    }

    private function createProductFolderStructure(string $productIdentifier, string $category): Folder
    {
        $productsFolder = Folder::getById(self::PRODUCTS_MAIN_FOLDER_ID);
        if (!$productsFolder) {
            throw new \Exception('Products main folder not found');
        }
        $categoryFolder = $this->getOrCreateFolder($productsFolder, $category);
        $identifierPrefix = strtoupper(explode('-', $productIdentifier)[0]);
        $identifierFolder = $this->getOrCreateFolder($categoryFolder, $identifierPrefix);
        return $identifierFolder;
    }

    private function getOrCreateFolder(Folder $parent, string $folderName): Folder
    {
        $folderPath = $parent->getFullPath() . '/' . $folderName;
        $folder = Folder::getByPath($folderPath);
        if (!$folder) {
            $folder = new Folder();
            $folder->setKey($folderName);
            $folder->setParent($parent);
            $folder->save();
        }
        return $folder;
    }

}