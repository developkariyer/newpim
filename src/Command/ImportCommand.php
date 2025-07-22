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
        // $imageAsset = null;
        // $data['image'] = 'https://iwa.web.tr/' . $data['image'];
        // if ($data['image']) {
        //     $imageAsset = $this->assetService->uploadProductImage(
        //         $data['image'], 
        //         $data['identifier'] ?: $data['name']
        //     );
        // }
        $parentFolder = $this->createProductFolderStructure($data['identifier'], $data['category']);
        $product = new Product();
        $product->setParent($parentFolder);
        $product->setKey($data['identifier'] . ' ' . $data['name']);
        $product->setProductIdentifier($data['identifier']);
        $product->setName($data['name']);
        $product->setDescription($data['description']);
        $product->setProductCategory($this->getProductCategory($data['category']));
        $product->setProductCode($data['productCode']);
        // if ($imageAsset) {
        //     $product->setImage($imageAsset);
        // }

    }

    private function getProductCategory(string $categoryName)
    {
        $category = Category::getByKey($categoryName);
        if ($category instanceof Category) {
            return $category;
        }
        return null;
        
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
