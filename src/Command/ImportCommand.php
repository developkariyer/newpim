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

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $filePath = PIMCORE_PROJECT_ROOT . '/tmp/exportProduct.json';
        if (!file_exists($filePath)) {
            $output->writeln('<error>File not found: ' . $filePath . '</error>');
            return Command::FAILURE;
        }
        $jsonContent = file_get_contents($filePath);
        $data = json_decode($jsonContent, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $output->writeln('<error>JSON decode error: ' . json_last_error_msg() . '</error>');
            return Command::FAILURE;
        }
        if (!is_array($data)) {
            $output->writeln('<error>Decoded JSON is not an array.</error>');
            return Command::FAILURE;
        }
        $output->writeln('<info>Products:</info>');
        //$this->createAsinFnsku($data);

        $count = 0;
        foreach ($data as $index => $product) {
            // if ($count >= 500) {
            //     break;
            // }
            $this->createProduct($product);
            $count++;
        }
        return Command::SUCCESS;
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
                    !empty($variant['ean']) && // BOŞLARI ATLAR
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

    private function createProduct(array $data)
    {
        $isExist = $this->checkExistProduct($data['identifier']);
        if ($isExist) {
            echo 'Product with identifier ' . $data['identifier'] . ' already exists.' . PHP_EOL;
            $this->updateProduct($data);
            return;
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
        $product = new Product();
        $product->setParent($parentFolder);
        $key = $data['identifier'] . ' ' . $data['name'];
        if (empty(trim($key))) {
            echo 'Product key is empty for identifier ' . $data['identifier'] . ', skipping.' . PHP_EOL;
            return;
        }
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
        $this->createVariant($product, $data['variants'] ?? []);
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

    private function createVariant($parentProduct, $data)
    {
        if (!is_array($data) || empty($data)) {
            return;
        }
        foreach ($data as $variantData) {
            $variant = new Product();
            $variant->setParent($parentProduct);
            $variant->setType(Product::OBJECT_TYPE_VARIANT);
            $variant->setProductCode($variantData['productCode']);
            $variant->setIwasku($variantData['iwasku']);
            if (empty(trim($variantData['key']))) {
                echo 'Variant key is empty for product ' . $parentProduct->getProductIdentifier() . ', skipping.' . PHP_EOL;
                continue;
            }
            $variant->setKey($variantData['key']);
            $variant->setName($variantData['name']);
            $color = $this->createColor($variantData['variationColor']);
            if ($color) {
                $variant->setVariationColor($color);
            } else {
                echo 'Skipping variant due to empty color for product ' . $parentProduct->getProductIdentifier() . PHP_EOL;
                continue;
            }
            $variant->setVariationSize($variantData['variationSize'] ?? '');
            $variant->setCustomField($variantData['customField'] ?? '');
            $variant->setPublished($variantData['published']);
            $variant->save();
        }
       
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
        $result = [];
        foreach ($sizeTable as $row) {
            if (empty($row)) {
                continue; 
            }

            $label = (string)array_pop($row); 

            $result[] = [
                'label'  => $label,
                'width'  => (string)($row[0] ?? '0'),
                'length' => (string)($row[1] ?? '0'),
                'height' => (string)($row[2] ?? '0'),
            ];
        }
        return $result;
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