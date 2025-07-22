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
        foreach ($data as $index => $product) {
            $this->createProduct($product);

        }
        return Command::SUCCESS;
    }

    private function createProduct(array $data)
    {
        $imageAsset = null;
        $data['image'] = 'https://iwa.web.tr' . $data['image'];
        echo $data['image'] . PHP_EOL;
        if ($data['image']) {
            $imageName = $data['identifier'] ?: $data['name'];
            if (!str_ends_with($imageName, '.png')) {
                $imageName .= '.png';
            }
            echo $imageName . PHP_EOL;
            $uploadedFile = $this->createUploadedFileFromUrl($data['image'], $imageName);
            $imageAsset = $this->assetService->uploadProductImage(
                $uploadedFile,
                $imageName
            );
        }

        $parentFolder = $this->createProductFolderStructure($data['identifier'], $data['category']);
        $product = new Product();
        $product->setParent($parentFolder);
        $product->setKey($data['identifier'] . ' ' . $data['name']);
        $product->setProductIdentifier($data['identifier']);
        $product->setName($data['name']);
        $product->setDescription($data['description']);
        $product->setProductCategory($this->getProductCategory($data['category']));
        $product->setProductCode($data['productCode']);

        $product->setMarketplaces($this->getMarketplaceObjects($data['variants'] ?? []));
        $product->setVariationSizeTable($this->createSizeTable($data['sizeTable'] ?? []));
        $product->setPublished($data['published'] ?? true);
        if ($imageAsset) {
            $product->setImage($imageAsset);
        }

        $product->save();

        // brands colors size tables variants 
        

    }

    private function createSizeTable($sizeTable): array
    {
        $result = [];
        foreach ($sizeTable as $row) {
            if (count($row) >= 3) {
                $result[] = [
                    'label' => (string)$row[2],
                    'width'    => (string)$row[0],
                    'length'   => (string)$row[1],
                    'height' => (string)$row[3] ?? 0,
                ];
            }
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

    private function createUploadedFileFromUrl(string $url, string $name = null): UploadedFile
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'upl_');
        file_put_contents($tmpFile, file_get_contents($url));
        $originalName = $name ?: basename(parse_url($url, PHP_URL_PATH));
        $mimeType = mime_content_type($tmpFile);
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
