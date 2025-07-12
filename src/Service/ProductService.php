<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Pimcore\Model\DataObject\Product;
use Pimcore\Model\DataObject\Category;
use Pimcore\Model\DataObject\Folder;
use Symfony\Component\HttpFoundation\Request;
use App\Service\AssetManagementService;
use App\Service\DataProcessingService;
use App\Service\CodeGenerationService;
use App\Service\VariantService;

class ProductService
{
    private const PRODUCTS_MAIN_FOLDER_ID = 1246;
    private const CLASS_MAPPING = [
        'color' => 'Pimcore\Model\DataObject\Color',
        'brand' => 'Pimcore\Model\DataObject\Brand',
        'marketplace' => 'Pimcore\Model\DataObject\Marketplace',
        'category' => 'Pimcore\Model\DataObject\Category'
    ];

    public function __construct(
        private LoggerInterface $logger,
        private AssetManagementService $assetService,
        private DataProcessingService $dataProcessor,
        private CodeGenerationService $codeGenerator,
        private VariantService $variantService
    ) {}

    public function processProduct(array $requestData): array
    {
        $validationResult = $this->validateProductData($requestData);
        if (!$validationResult['isValid']) {
            return [
                'success' => false,
                'errors' => $validationResult['errors']
            ];
        }
        try {
            $product = $requestData['isUpdate'] 
                ? $this->updateExistingProduct($requestData)
                : $this->createNewProduct($requestData);
            
            $this->setProductData($product, $requestData, $validationResult['objects']);
            $product->setPublished(true);
            $product->save();
            $this->logger->info('Ürün kaydedildi', ['productId' => $product->getId()]);
            if (!empty($requestData['variations'])) {
                $this->variantService->createProductVariants($product, $requestData['variations']);
            }
            return [
                'success' => true,
                'product' => $product,
                'message' => $requestData['isUpdate'] 
                    ? 'Ürün başarıyla güncellendi' 
                    : 'Ürün başarıyla oluşturuldu'
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'exception' => $e
            ];
        }
    }

    public function validateProductData(array $data): array
    {
        $errors = [];
        $objects = [];
        $objects['category'] = $this->validateSingleObject('category', $data['categoryId'], $errors, 'Kategori');
        $objects['brands'] = $this->validateMultipleObjects('brand', $data['brandIds'], $errors, 'Marka');
        $objects['marketplaces'] = $this->validateMultipleObjects('marketplace', $data['marketplaceIds'], $errors, 'Pazaryeri');
        $objects['colors'] = $this->validateMultipleObjects('color', $data['colorIds'], $errors, 'Renk');
        if (!$data['isUpdate'] && (!$data['imageFile'] || !$data['imageFile']->isValid())) {
            $errors[] = 'Geçerli bir resim dosyası gerekli.';
        }
        return [
            'isValid' => empty($errors),
            'errors' => $errors,
            'objects' => $objects
        ];
    }

    private function validateSingleObject(string $type, $id, array &$errors, string $displayName): ?object
    {
        if (empty($id)) {
            return null;
        }
        $intId = (int)(is_array($id) ? $id[0] : $id);
        $className = self::CLASS_MAPPING[$type];
        try {
            $object = $className::getById($intId);
            if (!$object) {
                $errors[] = "{$displayName} ID {$intId} bulunamadı";
            }
            return $object;
        } catch (\Exception $e) {
            $errors[] = "{$displayName} ID {$intId} bulunamadı";
            return null;
        }
    }

    private function validateMultipleObjects(string $type, array $ids, array &$errors, string $displayName): array
    {
        if (empty($ids)) {
            return [];
        }
        $cleanIds = array_map('intval', array_filter($ids, 'is_numeric'));
        if (empty($cleanIds)) {
            return [];
        }
        $className = self::CLASS_MAPPING[$type];
        $objects = [];
        foreach (array_unique($cleanIds) as $id) {
            try {
                $object = $className::getById($id);
                if ($object) {
                    $objects[] = $object;
                } else {
                    $errors[] = "{$displayName} ID {$id} bulunamadı";
                }
            } catch (\Exception $e) {
                $this->logger->warning('Error validating multiple object', [
                    'type' => $type,
                    'id' => $id,
                    'error' => $e->getMessage()
                ]);
                $errors[] = "{$displayName} ID {$id} bulunamadı";
            }
        }
        return $objects;
    }

    /**
     * Yeni ürün oluşturur
     */
    private function createNewProduct(array $data): Product
    {
        $imageAsset = null;
        if ($data['imageFile'] && $data['imageFile']->isValid()) {
            $imageAsset = $this->assetService->uploadProductImage(
                $data['imageFile'], 
                $data['productIdentifier'] ?: $data['productName']
            );
        }
        $parentFolder = $this->createProductFolderStructure($data['productIdentifier'], $data['categoryId']);
        $product = new Product();
        $product->setParent($parentFolder);
        $product->setKey($data['productIdentifier'] . ' ' . $data['productName']);
        $product->setProductIdentifier($data['productIdentifier']);
        if ($imageAsset) {
            $product->setImage($imageAsset);
        }
        return $product;
    }

    private function updateExistingProduct(array $data): Product
    {
        $product = Product::getById($data['editingProductId']);
        if (!$product) {
            throw new \Exception('Güncellenecek ürün bulunamadı.');
        }
        if ($data['imageFile'] && $data['imageFile']->isValid()) {
            $imageAsset = $this->assetService->uploadProductImage(
                $data['imageFile'], 
                $product->getProductIdentifier()
            );
            
            if ($imageAsset) {
                $product->setImage($imageAsset);
            }
        }
        
        return $product;
    }

    private function setProductData(Product $product, array $data, array $objects): void
    {
        $product->setName($data['productName']);
        $product->setDescription($data['productDescription']);
        $product->setProductCategory($objects['category']);
        $product->setBrandItems($objects['brands']);
        $product->setMarketplaces($objects['marketplaces']);
        if ($data['sizeTableData']) {
            $sizeTable = json_decode($data['sizeTableData'], true);
            if (is_array($sizeTable)) {
                $product->setVariationSizeTable($sizeTable);
            }
        }
        if ($data['customTableData']) {
            $customTable = $this->dataProcessor->processCustomTableData($data['customTableData']);
            if ($customTable) {
                $product->setCustomFieldTable($customTable);
            }
        }
        if (!$data['isUpdate']) {
            $this->codeGenerator->generateProductCode($product);
        }
    }

    private function createProductFolderStructure(string $productIdentifier, int $categoryId): Folder
    {
        $productsFolder = Folder::getById(self::PRODUCTS_MAIN_FOLDER_ID);
        if (!$productsFolder) {
            throw new \Exception('Products main folder not found');
        }
        $category = Category::getById($categoryId);
        if (!$category) {
            throw new \Exception('Category not found');
        }
        $categoryFolder = $this->getOrCreateFolder($productsFolder, $category->getKey());
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

    public function getProductById(int $id): ?Product
    {
        try {
            return Product::getById($id);
        } catch (\Exception $e) {
            return null;
        }
    }
}