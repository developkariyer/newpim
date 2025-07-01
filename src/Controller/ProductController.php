<?php
// filepath: \\wsl.localhost\Ubuntu-24.04\var\www\github\newpim\src\Controller\ProductController.php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Pimcore\Model\DataObject\Product;
use Pimcore\Model\DataObject\Brand;
use Pimcore\Model\DataObject\Category;
use Pimcore\Model\DataObject\Marketplace;
use Pimcore\Model\DataObject\Color;
use Pimcore\Model\DataObject\Category\Listing as CategoryListing;
use Pimcore\Model\DataObject\Color\Listing as ColorListing;
use Pimcore\Model\DataObject\Brand\Listing as BrandListing;
use Pimcore\Model\DataObject\Marketplace\Listing as MarketplaceListing;
use Pimcore\Model\DataObject\Product\Listing as ProductListing;

class ProductController extends AbstractController
{
    private const MAIN_FOLDER_ID = 1246;
    private const COLOR_FOLDER_ID = 1247;
    
    private const TYPE_MAPPING = [
        'colors' => ColorListing::class,
        'brands' => BrandListing::class,
        'marketplaces' => MarketplaceListing::class,
        'categories' => CategoryListing::class
    ];

    private const CLASS_MAPPING = [
        'color' => Color::class,
        'brand' => Brand::class,
        'marketplace' => Marketplace::class,
        'category' => Category::class
    ];

    #[Route('/product', name: 'product')]
    public function index(): Response
    {
        $viewData = [
            'categories' => $this->getCategoryList(),
            'colors' => $this->getGenericListing(self::TYPE_MAPPING['colors']),
            'brands' => $this->getGenericListing(self::TYPE_MAPPING['brands']),
            'marketplaces' => $this->getGenericListing(self::TYPE_MAPPING['marketplaces'])
        ];

        return $this->render('product/product.html.twig', $viewData);
    }

    #[Route('/product/search/{type}', name: 'product_search', methods: ['GET'])]
    public function search(Request $request, string $type): JsonResponse
    {
        if (!$this->isValidSearchType($type)) {
            return new JsonResponse(['error' => 'Invalid search type'], 400);
        }

        $query = trim($request->query->get('q', ''));
        $searchCondition = $this->buildSearchCondition($query);
        $results = $this->getGenericListing(self::TYPE_MAPPING[$type], $searchCondition);

        return new JsonResponse(['items' => $results]);
    }

    #[Route('/product/search-products', name: 'product_search_products', methods: ['GET'])]
    public function searchProducts(Request $request): JsonResponse
    {
        try {
            $query = trim($request->query->get('q', ''));
            
            if (strlen($query) < 2) {
                return new JsonResponse(['items' => []]);
            }

            $product = $this->findProductByQuery($query);
            
            if (!$product) {
                return new JsonResponse(['items' => []]);
            }

            $productData = $this->buildProductResponse($product);
            
            return new JsonResponse(['items' => [$productData]]);
            
        } catch (\Exception $e) {
            error_log('Product search error: ' . $e->getMessage());
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/product/create', name: 'product_create', methods: ['POST'])]
    public function create(Request $request): Response
    {
        try {
            $productData = $this->extractProductData($request);
            $validationErrors = $this->validateProductData($productData);

            if (!empty($validationErrors)) {
                $this->addFlashErrors($validationErrors);
                return $this->redirectToRoute('product');
            }

            $product = $this->createOrUpdateProduct($productData);
            $this->addProductVariants($product, $productData['variations']);

            $message = $productData['isUpdate'] ? 'Ürün başarıyla güncellendi.' : 'Ürün başarıyla oluşturuldu.';
            $this->addFlash('success', $message);

            return $this->redirectToRoute('product');

        } catch (\Throwable $e) {
            $errorMessage = 'Ürün işlenirken bir hata oluştu: ' . $e->getMessage();
            $this->addFlash('danger', $errorMessage);
            
            return $this->render('product/product.html.twig', [
                'errors' => [$errorMessage]
            ]);
        }
    }

    #[Route('/product/add-color', name: 'product_add_color', methods: ['POST'])]
    public function addColor(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $colorName = trim($data['name'] ?? '');

        if (!$colorName) {
            return new JsonResponse(['success' => false, 'message' => 'Renk adı boş olamaz.']);
        }

        if ($this->colorExists($colorName)) {
            return new JsonResponse(['success' => false, 'message' => 'Bu renk zaten mevcut.']);
        }

        $color = $this->createColor($colorName);
        
        return new JsonResponse(['success' => true, 'id' => $color->getId()]);
    }

    // Private Methods - Data Extraction & Validation
    private function extractProductData(Request $request): array
    {
        return [
            'editingProductId' => $request->get('editingProductId'),
            'isUpdate' => !empty($request->get('editingProductId')),
            'name' => $request->get('productName'),
            'identifier' => $request->get('productIdentifier'),
            'description' => $request->get('productDescription'),
            'imageFile' => $request->files->get('productImage'),
            'categoryId' => $request->get('productCategory'),
            'brandIds' => $request->get('brands', []),
            'marketplaceIds' => $request->get('marketplaces', []),
            'colorIds' => $request->get('colors', []),
            'sizeTableData' => $request->get('sizeTableData'),
            'customTableData' => $request->get('customTableData'),
            'variations' => json_decode($request->get('variationsData'), true) ?? []
        ];
    }

    private function validateProductData(array $productData): array
    {
        $errors = [];
        
        $category = $this->validateSingleObject('category', $productData['categoryId'], $errors, 'Kategori');
        $brands = $this->validateMultipleObjects('brand', $productData['brandIds'], $errors, 'Marka');
        $marketplaces = $this->validateMultipleObjects('marketplace', $productData['marketplaceIds'], $errors, 'Pazaryeri');
        $colors = $this->validateMultipleObjects('color', $productData['colorIds'], $errors, 'Renk');

        return [
            'errors' => $errors,
            'category' => $category,
            'brands' => $brands,
            'marketplaces' => $marketplaces,
            'colors' => $colors
        ];
    }

    // Private Methods - Product Operations
    private function createOrUpdateProduct(array $productData): Product
    {
        $validatedData = $this->validateProductData($productData);
        
        if ($productData['isUpdate']) {
            $product = $this->getExistingProduct($productData['editingProductId']);
        } else {
            $product = $this->createNewProduct($productData);
        }

        $this->updateProductProperties($product, $productData, $validatedData);
        $this->updateProductTables($product, $productData);
        
        if (!$productData['isUpdate']) {
            $this->generateProductCode($product);
        }

        $product->setPublished(true);
        $product->save();

        return $product;
    }

    private function getExistingProduct(string $productId): Product
    {
        $product = Product::getById($productId);
        
        if (!$product) {
            throw new \Exception('Güncellenecek ürün bulunamadı.');
        }

        return $product;
    }

    private function createNewProduct(array $productData): Product
    {
        $parentFolder = $this->getOrCreateProductFolder($productData['identifier']);
        
        $product = new Product();
        $product->setParent($parentFolder);
        $product->setKey($productData['identifier'] . ' ' . $productData['name']);
        $product->setProductIdentifier($productData['identifier']);

        if ($productData['imageFile'] && $productData['imageFile']->isValid()) {
            $imageAsset = $this->uploadProductImage($productData['imageFile'], $productData['identifier']);
            if ($imageAsset) {
                $product->setImage($imageAsset);
            }
        }

        return $product;
    }

    private function updateProductProperties(Product $product, array $productData, array $validatedData): void
    {
        $product->setName($productData['name']);
        $product->setDescription($productData['description']);
        $product->setProductCategory($validatedData['category']);
        $product->setBrandItems($validatedData['brands']);
        $product->setMarketplaces($validatedData['marketplaces']);

        // Update image for existing product
        if ($productData['isUpdate'] && $productData['imageFile'] && $productData['imageFile']->isValid()) {
            $imageAsset = $this->uploadProductImage($productData['imageFile'], $product->getProductIdentifier());
            if ($imageAsset) {
                $product->setImage($imageAsset);
            }
        }
    }

    private function updateProductTables(Product $product, array $productData): void
    {
        $this->updateSizeTable($product, $productData['sizeTableData']);
        $this->updateCustomTable($product, $productData['customTableData']);
    }

    private function updateSizeTable(Product $product, ?string $sizeTableData): void
    {
        if (!$sizeTableData) return;

        $sizeTable = json_decode($sizeTableData, true);
        
        if (is_array($sizeTable)) {
            $product->setVariationSizeTable($sizeTable);
        }
    }

    private function updateCustomTable(Product $product, ?string $customTableData): void
    {
        if (!$customTableData) return;

        $customTableDecoded = json_decode($customTableData, true);
        
        if (!$this->isValidCustomTableData($customTableDecoded)) return;

        $customFieldTable = $this->buildCustomFieldTable($customTableDecoded);
        $product->setCustomFieldTable($customFieldTable);
    }

    private function isValidCustomTableData($data): bool
    {
        return is_array($data) && 
               isset($data['rows']) && 
               is_array($data['rows']);
    }

    private function buildCustomFieldTable(array $customTableDecoded): array
    {
        $customFieldTable = [];
        $title = trim($customTableDecoded['title'] ?? '');

        // Add title as first row
        if ($title !== '') {
            $customFieldTable[] = ['deger' => $title];
        }

        // Add data rows
        foreach ($customTableDecoded['rows'] as $row) {
            if (isset($row['deger']) && !empty($row['deger'])) {
                $customFieldTable[] = ['deger' => $row['deger']];
            }
        }

        return $customFieldTable;
    }

    // Private Methods - Product Response Building
    private function buildProductResponse(Product $product): array
    {
        $variants = $this->getProductVariants($product);
        $variantData = $this->extractVariantData($variants);
        
        return [
            'id' => $product->getId(),
            'name' => $product->getName(),
            'productIdentifier' => $product->getProductIdentifier(),
            'description' => $product->getDescription(),
            'categoryId' => $product->getProductCategory()?->getId(),
            'categoryName' => $product->getProductCategory()?->getKey(),
            'brands' => $this->getBrandData($product),
            'marketplaces' => $this->getMarketplaceData($product),
            'imagePath' => $product->getImage()?->getFullPath(),
            'hasVariants' => $product->hasChildren(),
            'variants' => $variantData['variants'],
            'variantColors' => array_values(array_unique($variantData['variantColors'], SORT_REGULAR)),
            'sizeTable' => $this->getSizeTableData($product, $variantData['usedSizes']),
            'customTable' => $this->getCustomTableData($product, $variantData['usedCustoms']),
            'usedSizes' => array_values(array_unique($variantData['usedSizes'])),
            'usedCustoms' => array_values(array_unique($variantData['usedCustoms'])),
            'usedColorIds' => array_values(array_unique($variantData['usedColorIds'])),
            'canEditSizeTable' => true,
            'canEditColors' => true,
            'canEditCustomTable' => true,
            'canCreateVariants' => true
        ];
    }

    private function getProductVariants(Product $product): array
    {
        if (!$product->hasChildren()) {
            return [];
        }

        return $product->getChildren([Product::OBJECT_TYPE_VARIANT]);
    }

    private function extractVariantData(array $variants): array
    {
        $variantData = [
            'variants' => [],
            'variantColors' => [],
            'usedSizes' => [],
            'usedCustoms' => [],
            'usedColorIds' => []
        ];

        foreach ($variants as $variant) {
            $variantInfo = $this->buildVariantInfo($variant);
            $variantData['variants'][] = $variantInfo;

            $this->collectVariantMetadata($variant, $variantData);
        }

        return $variantData;
    }

    private function buildVariantInfo($variant): array
    {
        return [
            'id' => $variant->getId(),
            'name' => $variant->getName(),
            'color' => $variant->getVariationColor()?->getColor(),
            'colorId' => $variant->getVariationColor()?->getId(),
            'size' => $variant->getVariationSize(),
            'custom' => $variant->getCustomField(),
        ];
    }

    private function collectVariantMetadata($variant, array &$variantData): void
    {
        // Collect color data
        if ($variant->getVariationColor()) {
            $colorId = $variant->getVariationColor()->getId();
            $variantData['usedColorIds'][] = $colorId;
            $variantData['variantColors'][] = [
                'id' => $colorId,
                'name' => $variant->getVariationColor()->getColor()
            ];
        }

        // Collect size data
        if ($variant->getVariationSize()) {
            $variantData['usedSizes'][] = $variant->getVariationSize();
        }

        // Collect custom data
        if ($variant->getCustomField()) {
            $variantData['usedCustoms'][] = $variant->getCustomField();
        }
    }

    private function getSizeTableData(Product $product, array $usedSizes): array
    {
        $sizeTableData = $product->getVariationSizeTable();
        
        if (!$sizeTableData || !is_array($sizeTableData)) {
            return [];
        }

        $sizeTable = [];
        
        foreach ($sizeTableData as $row) {
            $beden = $row['beden'] ?? '';
            $sizeTable[] = [
                'beden' => $beden,
                'en' => $row['en'] ?? '',
                'boy' => $row['boy'] ?? '',
                'yukseklik' => $row['yukseklik'] ?? '',
                'birim' => $row['birim'] ?? '',
                'locked' => in_array($beden, $usedSizes)
            ];
        }

        return $sizeTable;
    }

    private function getCustomTableData(Product $product, array $usedCustoms): array
    {
        $customTableData = $product->getCustomFieldTable();
        
        if (!$customTableData || !is_array($customTableData)) {
            return ['title' => '', 'rows' => []];
        }

        $customTitle = '';
        $customRows = [];

        foreach ($customTableData as $index => $row) {
            $deger = $this->extractDegerValue($row);
            
            if (empty($deger)) continue;

            if ($index === 0) {
                $customTitle = $deger; // First row is title
            } else {
                $customRows[] = [
                    'deger' => $deger,
                    'locked' => in_array($deger, $usedCustoms)
                ];
            }
        }

        return [
            'title' => $customTitle,
            'rows' => $customRows
        ];
    }

    private function extractDegerValue(array $row): string
    {
        if (isset($row['deger'])) {
            return is_string($row['deger']) ? $row['deger'] : (string)$row['deger'];
        }
        
        if (isset($row['value'])) {
            return is_string($row['value']) ? $row['value'] : (string)$row['value'];
        }

        return '';
    }

    // Private Methods - Variant Operations
    private function addProductVariants(Product $parentProduct, array $variations): void
    {
        foreach ($variations as $variantData) {
            $this->createProductVariant($parentProduct, $variantData);
        }
    }

    private function createProductVariant(Product $parentProduct, array $variantData): void
    {
        $variant = new Product();
        $variant->setParent($parentProduct);
        $variant->setType(Product::OBJECT_TYPE_VARIANT);
        
        $variantKey = $this->generateVariantKey($parentProduct, $variantData);
        $variant->setKey($variantKey);
        $variant->setName($variantKey);

        $this->setVariantProperties($variant, $variantData);
        $this->generateVariantCodes($parentProduct->getProductIdentifier(), $variant);
        
        $variant->setPublished(true);
        $variant->save();
    }

    private function generateVariantKey(Product $parentProduct, array $variantData): string
    {
        $keyParts = array_filter([
            $variantData['renk'] ?? '',
            $variantData['beden'] ?? '',
            $variantData['custom'] ?? ''
        ]);
        
        $variantSuffix = !empty($keyParts) ? implode('-', $keyParts) : 'variant';
        
        return sprintf(
            '%s-%s-%s',
            $parentProduct->getProductIdentifier(),
            $parentProduct->getName(),
            $variantSuffix
        );
    }

    private function setVariantProperties(Product $variant, array $variantData): void
    {
        if (!empty($variantData['renk'])) {
            $color = $this->findColorByName($variantData['renk']);
            if ($color) {
                $variant->setVariationColor($color);
            }
        }

        if (!empty($variantData['beden'])) {
            $variant->setVariationSize($variantData['beden']);
        }

        if (!empty($variantData['custom'])) {
            $variant->setCustomField($variantData['custom']);
        }
    }

    // Private Methods - Helper Functions
    private function findProductByQuery(string $query): ?Product
    {
        $listing = new ProductListing();
        $listing->setCondition('productIdentifier LIKE ? OR name LIKE ?', ["%$query%", "%$query%"]);
        $listing->setLimit(1);
        $products = $listing->load();

        return count($products) > 0 ? $products[0] : null;
    }

    private function findColorByName(string $colorName): ?Color
    {
        $colorListing = new ColorListing();
        $colorListing->setCondition('color = ?', [$colorName]);
        $colorListing->setLimit(1);
        
        return $colorListing->current();
    }

    private function getBrandData(Product $product): array
    {
        $brands = [];
        $brandItems = $product->getBrandItems();

        if (!$brandItems) return $brands;

        if (is_array($brandItems)) {
            foreach ($brandItems as $brand) {
                $brands[] = ['id' => $brand->getId(), 'name' => $brand->getKey()];
            }
        } elseif ($brandItems instanceof Brand) {
            $brands[] = ['id' => $brandItems->getId(), 'name' => $brandItems->getKey()];
        }

        return $brands;
    }

    private function getMarketplaceData(Product $product): array
    {
        $marketplaces = [];
        $marketplaceItems = $product->getMarketplaces();

        if (!$marketplaceItems) return $marketplaces;

        if (is_array($marketplaceItems)) {
            foreach ($marketplaceItems as $marketplace) {
                $marketplaces[] = ['id' => $marketplace->getId(), 'name' => $marketplace->getKey()];
            }
        } elseif ($marketplaceItems instanceof Marketplace) {
            $marketplaces[] = ['id' => $marketplaceItems->getId(), 'name' => $marketplaceItems->getKey()];
        }

        return $marketplaces;
    }

    private function getOrCreateProductFolder(string $productIdentifier): \Pimcore\Model\DataObject\Folder
    {
        $identifierPrefix = strtoupper(explode('-', $productIdentifier)[0]);
        $productsFolder = \Pimcore\Model\DataObject\Folder::getById(self::MAIN_FOLDER_ID);
        $parentFolderPath = $productsFolder->getFullPath() . '/' . $identifierPrefix;
        $parentFolder = \Pimcore\Model\DataObject\Folder::getByPath($parentFolderPath);
        
        if (!$parentFolder) {
            $parentFolder = new \Pimcore\Model\DataObject\Folder();
            $parentFolder->setKey($identifierPrefix);
            $parentFolder->setParent($productsFolder);
            $parentFolder->save();
        }

        return $parentFolder;
    }

    private function uploadProductImage($imageFile, string $productKey): ?\Pimcore\Model\Asset\Image
    {
        try {
            $assetFolder = $this->getOrCreateAssetFolder();
            $filename = $this->generateImageFilename($imageFile, $productKey);
            
            $imageAsset = new \Pimcore\Model\Asset\Image();
            $imageAsset->setFilename($filename);
            $imageAsset->setParent($assetFolder);
            
            $fileContent = file_get_contents($imageFile->getPathname());
            if ($fileContent === false) {
                throw new \Exception('Dosya içeriği okunamadı');
            }
            
            $imageAsset->setData($fileContent);
            $imageAsset->save();

            return $imageAsset;
        } catch (\Exception $e) {
            error_log('Image upload error: ' . $e->getMessage());
            return null;
        }
    }

    private function getOrCreateAssetFolder(): \Pimcore\Model\Asset\Folder
    {
        $assetFolder = \Pimcore\Model\Asset::getByPath('/products');
        
        if (!$assetFolder) {
            $assetFolder = new \Pimcore\Model\Asset\Folder();
            $assetFolder->setFilename('products');
            $assetFolder->setParent(\Pimcore\Model\Asset::getByPath('/'));
            $assetFolder->save();
        }

        return $assetFolder;
    }

    private function generateImageFilename($imageFile, string $productKey): string
    {
        $extension = $imageFile->getClientOriginalExtension() ?: 'jpg';
        $safeFilename = $this->generateSafeFilename($productKey);
        
        return $safeFilename . '_' . time() . '.' . $extension;
    }

    private function generateSafeFilename(string $input): string
    {
        $filename = mb_strtolower($input);
        $filename = str_replace(['ı', 'ğ', 'ü', 'ş', 'ö', 'ç'], ['i', 'g', 'u', 's', 'o', 'c'], $filename);
        $filename = preg_replace('/[^a-z0-9]/', '_', $filename);
        $filename = preg_replace('/_+/', '_', $filename);
        $filename = trim($filename, '_');
        
        return $filename ?: 'product';
    }

    // Code Generation Methods
    private function generateVariantCodes(string $parentIdentifier, Product $variant): void
    {
        if ($variant->getType() !== Product::OBJECT_TYPE_VARIANT) return;
        if (strlen($variant->getIwasku() ?? '') === 12) return;

        $iwasku = str_pad(str_replace('-', '', $parentIdentifier), 7, '0', STR_PAD_RIGHT);
        $productCode = $this->generateUniqueCode();
        
        $variant->setProductCode($productCode);
        $variant->setIwasku($iwasku . $productCode);
    }

    private function generateProductCode(Product $product): void
    {
        Product::setGetInheritedValues(false);
        
        if (strlen($product->getProductCode()) !== 5) {
            $productCode = $this->generateUniqueCode();
            $product->setProductCode($productCode);
        }
        
        Product::setGetInheritedValues(true);
    }

    private function generateUniqueCode(int $length = 5): string
    {
        do {
            $code = $this->generateRandomString($length);
        } while ($this->findByProductCode($code));

        return $code;
    }

    private function generateRandomString(int $length): string
    {
        $characters = 'ABCDEFGHJKMNPQRSTVWXYZ1234567890';
        $charactersLength = strlen($characters);
        $randomString = '';

        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[mt_rand(0, $charactersLength - 1)];
        }

        return $randomString;
    }

    private function findByProductCode(string $code): ?Product
    {
        $listing = new ProductListing();
        $listing->setCondition('productCode = ?', [$code]);
        $listing->setUnpublished(true);
        $listing->setLimit(1);
        
        return $listing->current();
    }

    // Validation Methods
    private function isValidSearchType(string $type): bool
    {
        return isset(self::TYPE_MAPPING[$type]);
    }

    private function buildSearchCondition(string $query): string
    {
        $escapedQuery = addslashes($query);
        return "published = 1 AND LOWER(`key`) LIKE LOWER('%{$escapedQuery}%')";
    }

    private function validateSingleObject(string $type, $id, array &$errors, string $displayName): ?object
    {
        if (empty($id)) return null;

        $idValue = is_array($id) ? $id[0] : $id;
        $intId = (int)$idValue;
        $object = $this->getObjectById(self::CLASS_MAPPING[$type], $intId);
        
        if (!$object) {
            $errors[] = "{$displayName} ID {$intId} bulunamadı";
        }

        return $object;
    }

    private function validateMultipleObjects(string $type, array $ids, array &$errors, string $displayName): array
    {
        if (empty($ids)) return [];

        $objects = [];
        
        foreach ($ids as $id) {
            if (empty($id)) continue;

            $idValue = is_array($id) ? $id[0] : $id;
            $intId = (int)$idValue;
            $object = $this->getObjectById(self::CLASS_MAPPING[$type], $intId);
            
            if (!$object) {
                $errors[] = "{$displayName} ID {$intId} bulunamadı";
            } else {
                $objects[] = $object;
            }
        }

        return $objects;
    }

    private function getObjectById(string $className, int $id): ?object
    {
        if (!class_exists($className)) return null;

        try {
            return $className::getById($id);
        } catch (\Exception $e) {
            return null;
        }
    }

    private function colorExists(string $colorName): bool
    {
        $listing = new ColorListing();
        $listing->setCondition('color = ?', [$colorName]);
        
        return $listing->count() > 0;
    }

    private function createColor(string $colorName): Color
    {
        $color = new Color();
        $color->setKey($colorName);
        $color->setParentId(self::COLOR_FOLDER_ID);
        $color->setColor($colorName);
        $color->setPublished(true);
        $color->save();

        return $color;
    }

    // Utility Methods
    private function getGenericListing(string $listingClass, string $condition = "published = 1"): array
    {
        $listing = new $listingClass();
        $listing->setCondition($condition);
        $listing->load();

        $resultList = [];
        foreach ($listing as $item) {
            $resultList[] = [
                'id' => $item->getId(),
                'name' => $item->getKey(),
            ];
        }

        return $resultList;
    }

    private function getCategoryList(): array
    {
        $categories = new CategoryListing();
        $categories->setCondition("published = 1");
        $categories->load();

        $categoryList = [];
        foreach ($categories as $category) {
            if ($category->hasChildren()) continue;

            $categoryList[] = [
                'id' => $category->getId(),
                'name' => $category->getKey(),
            ];
        }

        return $categoryList;
    }

    private function addFlashErrors(array $errors): void
    {
        foreach ($errors['errors'] as $error) {
            $this->addFlash('danger', $error);
        }
    }
}