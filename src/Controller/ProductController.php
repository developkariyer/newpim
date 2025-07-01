<?php

declare(strict_types=1);

namespace App\Controller;

use Pimcore\Model\DataObject\Brand;
use Pimcore\Model\DataObject\Brand\Listing as BrandListing;
use Pimcore\Model\DataObject\Category;
use Pimcore\Model\DataObject\Category\Listing as CategoryListing;
use Pimcore\Model\DataObject\Color;
use Pimcore\Model\DataObject\Color\Listing as ColorListing;
use Pimcore\Model\DataObject\Marketplace;
use Pimcore\Model\DataObject\Marketplace\Listing as MarketplaceListing;
use Pimcore\Model\DataObject\Product;
use Pimcore\Model\DataObject\Product\Listing as ProductListing;
use Pimcore\Model\Asset\Image as AssetImage;
use Pimcore\Model\Asset\Folder as AssetFolder;
use Pimcore\Model\Asset;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/product', name: 'product_')]
class ProductController extends AbstractController
{
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

    private const MAIN_FOLDER_ID = 1246;
    private const COLOR_PARENT_ID = 1247;
    private const PRODUCT_CODE_LENGTH = 5;
    private const IWASKU_LENGTH = 12;

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('product/product.html.twig');
    }

    #[Route('/search/{type}', name: 'search_relations', methods: ['GET'])]
    public function searchRelations(Request $request, string $type): JsonResponse
    {
        try {
            $query = trim($request->query->get('q', ''));
            
            if (!isset(self::TYPE_MAPPING[$type])) {
                return new JsonResponse(['error' => 'Invalid search type'], Response::HTTP_BAD_REQUEST);
            }

            $condition = "published = 1";
            if (!empty($query)) {
                $escapedQuery = addslashes($query);
                $condition .= " AND LOWER(`key`) LIKE LOWER('%{$escapedQuery}%')";
            }

            $results = $this->getGenericListing(self::TYPE_MAPPING[$type], $condition);
            return new JsonResponse(['items' => $results]);

        } catch (\Throwable $e) {
            error_log("Search relations error: " . $e->getMessage());
            return new JsonResponse(['error' => 'Search failed'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/search-products', name: 'search_products', methods: ['GET'])]
    public function searchProducts(Request $request): JsonResponse
    {
        try {
            $query = trim($request->query->get('q', ''));
            
            if (mb_strlen($query) < 2) {
                return new JsonResponse(['items' => []]);
            }

            $listing = new ProductListing();
            $listing->setCondition('productIdentifier LIKE ? OR name LIKE ?', ["%$query%", "%$query%"]);
            $listing->setLimit(1);
            $products = $listing->load();

            if (empty($products)) {
                return new JsonResponse(['items' => []]);
            }

            $product = $products[0];
            $productData = $this->buildProductData($product);

            return new JsonResponse(['items' => [$productData]]);

        } catch (\Throwable $e) {
            error_log("Product search error: " . $e->getMessage());
            return new JsonResponse(['error' => 'Product search failed'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/save', name: 'save', methods: ['POST'])]
    public function save(Request $request): Response
    {
        try {
            $productData = $this->extractRequestData($request);
            $imageFile = $request->files->get('productImage');
            
            $validationErrors = $this->validateProductData($productData);
            if (!empty($validationErrors)) {
                foreach ($validationErrors as $error) {
                    $this->addFlash('danger', $error);
                }
                return $this->redirectToRoute('product_index');
            }

            $isUpdate = !empty($productData['editingProductId']);
            $product = $this->saveProduct($productData, $imageFile, $isUpdate);

            if ($product) {
                $this->saveProductVariants($product, $productData['variations'] ?? []);
                
                $message = $isUpdate 
                    ? 'Ürün başarıyla güncellendi ve yeni varyantlar eklendi.'
                    : 'Ürün ve varyantlar başarıyla oluşturuldu.';
                
                $this->addFlash('success', $message);
            } else {
                $this->addFlash('danger', 'Ürün kaydedilemedi.');
            }

            return $this->redirectToRoute('product_index');

        } catch (\Throwable $e) {
            error_log("Product save error: " . $e->getMessage());
            $this->addFlash('danger', 'Ürün kaydedilirken bir hata oluştu: ' . $e->getMessage());
            return $this->redirectToRoute('product_index');
        }
    }

    #[Route('/add-color', name: 'add_color', methods: ['POST'])]
    public function addColor(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            $colorName = trim($data['name'] ?? '');

            if (empty($colorName)) {
                return new JsonResponse(['success' => false, 'message' => 'Renk adı boş olamaz.'], Response::HTTP_BAD_REQUEST);
            }

            if ($this->colorExists($colorName)) {
                return new JsonResponse(['success' => false, 'message' => 'Bu renk zaten mevcut.'], Response::HTTP_BAD_REQUEST);
            }

            $color = $this->createColor($colorName);

            return new JsonResponse([
                'success' => true,
                'id' => $color->getId(),
                'name' => $color->getKey()
            ]);

        } catch (\Throwable $e) {
            error_log("Add color error: " . $e->getMessage());
            return new JsonResponse(['success' => false, 'message' => 'Renk eklenirken bir hata oluştu.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Request'ten ürün verilerini çıkarır ve düzenler
     */
    private function extractRequestData(Request $request): array
    {
        $data = $request->request->all();
        
        // JSON verilerini decode et
        $jsonFields = ['variationsData', 'sizeTableData', 'customTableData'];
        foreach ($jsonFields as $field) {
            if (isset($data[$field]) && is_string($data[$field])) {
                $data[$field] = json_decode($data[$field], true) ?: [];
            }
        }

        // Array alanları düzenle
        $arrayFields = ['brands', 'marketplaces', 'colors'];
        foreach ($arrayFields as $field) {
            if (!isset($data[$field]) || !is_array($data[$field])) {
                $data[$field] = [];
            }
        }

        return [
            'editingProductId' => $data['editingProductId'] ?? null,
            'productName' => trim($data['productName'] ?? ''),
            'productIdentifier' => trim($data['productIdentifier'] ?? ''),
            'productDescription' => trim($data['productDescription'] ?? ''),
            'categoryId' => $data['productCategory'] ?? null,
            'brandIds' => $data['brands'],
            'marketplaceIds' => $data['marketplaces'],
            'colorIds' => $data['colors'],
            'sizeTableData' => $data['sizeTableData'] ?? [],
            'customTableData' => $data['customTableData'] ?? [],
            'variations' => $data['variationsData'] ?? []
        ];
    }

    /**
     * Ürün verilerini doğrular
     */
    private function validateProductData(array $data): array
    {
        $errors = [];

        if (empty($data['productName'])) {
            $errors[] = 'Ürün adı gereklidir.';
        }

        if (empty($data['productIdentifier'])) {
            $errors[] = 'Ürün tanıtıcı gereklidir.';
        }

        $category = $this->validateSingleObject('category', $data['categoryId'], $errors, 'Kategori');
        $this->validateMultipleObjects('brand', $data['brandIds'], $errors, 'Marka');
        $this->validateMultipleObjects('marketplace', $data['marketplaceIds'], $errors, 'Pazaryeri');
        $this->validateMultipleObjects('color', $data['colorIds'], $errors, 'Renk');

        return $errors;
    }

    /**
     * Ürünü kaydeder (yeni veya güncelleme)
     */
    private function saveProduct(array $data, ?UploadedFile $imageFile, bool $isUpdate): ?Product
    {
        try {
            if ($isUpdate) {
                $product = Product::getById($data['editingProductId']);
                if (!$product) {
                    throw new \RuntimeException('Güncellenecek ürün bulunamadı.');
                }
            } else {
                $product = $this->createNewProduct($data, $imageFile);
            }

            $this->updateProductProperties($product, $data);
            
            if ($isUpdate && $imageFile && $imageFile->isValid()) {
                $imageAsset = $this->uploadProductImage($imageFile, $product->getProductIdentifier());
                if ($imageAsset) {
                    $product->setImage($imageAsset);
                }
            }

            $this->updateProductTables($product, $data);

            if (!$isUpdate) {
                $this->generateProductCode($product);
            }

            $product->setPublished(true);
            $product->save();

            return $product;

        } catch (\Throwable $e) {
            error_log("Save product error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Yeni ürün oluşturur
     */
    private function createNewProduct(array $data, ?UploadedFile $imageFile): Product
    {
        $imageAsset = null;
        if ($imageFile && $imageFile->isValid()) {
            $imageAsset = $this->uploadProductImage($imageFile, $data['productIdentifier']);
        }

        $parentFolder = $this->getOrCreateProductFolder($data['productIdentifier']);

        $product = new Product();
        $product->setParent($parentFolder);
        $product->setKey($data['productIdentifier'] . ' ' . $data['productName']);
        $product->setProductIdentifier($data['productIdentifier']);
        
        if ($imageAsset) {
            $product->setImage($imageAsset);
        }

        return $product;
    }

    /**
     * Ürün özelliklerini günceller
     */
    private function updateProductProperties(Product $product, array $data): void
    {
        $product->setName($data['productName']);
        $product->setDescription($data['productDescription']);

        $category = $this->getObjectById(self::CLASS_MAPPING['category'], (int)$data['categoryId']);
        $product->setProductCategory($category);

        $brands = $this->getMultipleObjects('brand', $data['brandIds']);
        $product->setBrandItems($brands);

        $marketplaces = $this->getMultipleObjects('marketplace', $data['marketplaceIds']);
        $product->setMarketplaces($marketplaces);
    }

    /**
     * Ürün tablolarını günceller (size table ve custom table)
     */
    private function updateProductTables(Product $product, array $data): void
    {
        if (!empty($data['sizeTableData']) && is_array($data['sizeTableData'])) {
            $product->setVariationSizeTable($data['sizeTableData']);
        }

        if (!empty($data['customTableData']) && is_array($data['customTableData'])) {
            $customFieldTable = $this->buildCustomFieldTable($data['customTableData']);
            $product->setCustomFieldTable($customFieldTable);
        }
    }

    /**
     * Custom field table yapısını oluşturur
     */
    private function buildCustomFieldTable(array $customTableData): array
    {
        $customFieldTable = [];
        
        $title = trim($customTableData['title'] ?? '');
        if (!empty($title)) {
            $customFieldTable[] = ['deger' => $title];
        }

        if (isset($customTableData['rows']) && is_array($customTableData['rows'])) {
            foreach ($customTableData['rows'] as $row) {
                if (isset($row['deger']) && !empty(trim($row['deger']))) {
                    $customFieldTable[] = ['deger' => trim($row['deger'])];
                }
            }
        }

        return $customFieldTable;
    }

    /**
     * Ürün varyantlarını kaydeder
     */
    private function saveProductVariants(Product $parentProduct, array $variations): void
    {
        if (empty($variations) || !is_array($variations)) {
            return;
        }

        foreach ($variations as $variantData) {
            $this->createProductVariant($parentProduct, $variantData);
        }
    }

    /**
     * Tek bir ürün varyantı oluşturur
     */
    private function createProductVariant(Product $parentProduct, array $variantData): void
    {
        try {
            $variant = new Product();
            $variant->setParent($parentProduct);
            $variant->setType(Product::OBJECT_TYPE_VARIANT);
            
            $variantKey = $this->buildVariantKey($parentProduct, $variantData);
            $variant->setKey($variantKey);
            $variant->setName($variantKey);

            $this->setVariantProperties($variant, $variantData);
            $this->generateVariantCodes($variant, $parentProduct->getProductIdentifier());

            $variant->setPublished(true);
            $variant->save();

        } catch (\Throwable $e) {
            error_log("Create variant error: " . $e->getMessage());
        }
    }

    /**
     * Varyant key'ini oluşturur
     */
    private function buildVariantKey(Product $parentProduct, array $variantData): string
    {
        $keyParts = array_filter([
            $variantData['renk'] ?? '',
            $variantData['beden'] ?? '',
            $variantData['custom'] ?? ''
        ]);

        $variantKey = implode('-', $keyParts) ?: 'variant';
        
        return sprintf('%s-%s-%s', 
            $parentProduct->getProductIdentifier(),
            $parentProduct->getName(),
            $variantKey
        );
    }

    /**
     * Varyant özelliklerini set eder
     */
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

    /**
     * Varyant için kod ve IWASKU oluşturur
     */
    private function generateVariantCodes(Product $variant, string $parentIdentifier): void
    {
        if ($variant->getType() === Product::OBJECT_TYPE_VARIANT) {
            $productCode = $this->generateUniqueCode(self::PRODUCT_CODE_LENGTH);
            $variant->setProductCode($productCode);

            $iwasku = str_pad(str_replace('-', '', $parentIdentifier), 7, '0', STR_PAD_RIGHT);
            $iwasku .= $productCode;
            $variant->setIwasku($iwasku);
        }
    }

    /**
     * Ürün için klasör getirir veya oluşturur
     */
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

    /**
     * Ürün için benzersiz kod üretir
     */
    private function generateProductCode(Product $product): void
    {
        Product::setGetInheritedValues(false);
        
        if (strlen($product->getProductCode() ?? '') !== self::PRODUCT_CODE_LENGTH) {
            $productCode = $this->generateUniqueCode(self::PRODUCT_CODE_LENGTH);
            $product->setProductCode($productCode);
        }
        
        Product::setGetInheritedValues(true);
    }

    /**
     * Benzersiz kod üretir
     */
    private function generateUniqueCode(int $length = self::PRODUCT_CODE_LENGTH): string
    {
        $attempts = 0;
        $maxAttempts = 100;

        while ($attempts < $maxAttempts) {
            $candidateCode = $this->generateRandomString($length);
            if (!$this->findByField('productCode', $candidateCode)) {
                return $candidateCode;
            }
            $attempts++;
        }

        throw new \RuntimeException('Benzersiz kod üretilemedi.');
    }

    /**
     * Rastgele string üretir
     */
    private function generateRandomString(int $length): string
    {
        $characters = 'ABCDEFGHJKMNPQRSTVWXYZ1234567890';
        $charactersLength = strlen($characters);
        $randomString = '';

        for ($i = 0; $i < $length; $i++) {
            $randomIndex = random_int(0, $charactersLength - 1);
            $randomString .= $characters[$randomIndex];
        }

        return $randomString;
    }

    /**
     * Ürün resmi yükler
     */
    private function uploadProductImage(UploadedFile $imageFile, string $productKey): ?AssetImage
    {
        try {
            $assetFolder = Asset::getByPath('/products');
            if (!$assetFolder) {
                $assetFolder = new AssetFolder();
                $assetFolder->setFilename('products');
                $assetFolder->setParent(Asset::getByPath('/'));
                $assetFolder->save();
            }

            $extension = $imageFile->getClientOriginalExtension() ?: 'jpg';
            $filename = $this->generateSafeFilename($productKey) . '_' . time() . '.' . $extension;

            $imageAsset = new AssetImage();
            $imageAsset->setFilename($filename);
            $imageAsset->setParent($assetFolder);

            $fileContent = file_get_contents($imageFile->getPathname());
            if ($fileContent === false) {
                throw new \RuntimeException('Dosya içeriği okunamadı');
            }

            $imageAsset->setData($fileContent);
            $imageAsset->save();

            return $imageAsset;

        } catch (\Throwable $e) {
            error_log("Image upload error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Güvenli dosya adı üretir
     */
    private function generateSafeFilename(string $input): string
    {
        $filename = mb_strtolower($input);
        $filename = str_replace(['ı', 'ğ', 'ü', 'ş', 'ö', 'ç'], ['i', 'g', 'u', 's', 'o', 'c'], $filename);
        $filename = preg_replace('/[^a-z0-9]/', '_', $filename);
        $filename = preg_replace('/_+/', '_', $filename);
        $filename = trim($filename, '_');
        
        return $filename ?: 'product';
    }

    /**
     * Renk oluşturur
     */
    private function createColor(string $colorName): Color
    {
        $color = new Color();
        $color->setKey($colorName);
        $color->setParentId(self::COLOR_PARENT_ID);
        $color->setColor($colorName);
        $color->setPublished(true);
        $color->save();

        return $color;
    }

    /**
     * Rengin var olup olmadığını kontrol eder
     */
    private function colorExists(string $colorName): bool
    {
        $listing = new ColorListing();
        $listing->setCondition('color = ?', [$colorName]);
        
        return $listing->count() > 0;
    }

    /**
     * Rengi ismine göre bulur
     */
    private function findColorByName(string $colorName): ?Color
    {
        $listing = new ColorListing();
        $listing->setCondition('color = ?', [$colorName]);
        $listing->setLimit(1);
        
        return $listing->current() ?: null;
    }

    /**
     * Ürün datasını build eder (search için)
     */
    private function buildProductData(Product $product): array
    {
        $variants = $this->getProductVariants($product);
        $variantAnalysis = $this->analyzeVariants($variants);

        return [
            'id' => $product->getId(),
            'name' => $product->getName(),
            'productIdentifier' => $product->getProductIdentifier(),
            'description' => $product->getDescription(),
            'categoryId' => $product->getProductCategory()?->getId(),
            'categoryName' => $product->getProductCategory()?->getKey(),
            'brands' => $this->formatRelationArray($product->getBrandItems()),
            'marketplaces' => $this->formatRelationArray($product->getMarketplaces()),
            'imagePath' => $product->getImage()?->getFullPath(),
            'hasVariants' => $product->hasChildren(),
            'variants' => $variants,
            'variantColors' => array_values(array_unique($variantAnalysis['colors'], SORT_REGULAR)),
            'sizeTable' => $this->buildSizeTableData($product, $variantAnalysis['usedSizes']),
            'customTable' => $this->buildCustomTableData($product, $variantAnalysis['usedCustoms']),
            'usedSizes' => array_unique($variantAnalysis['usedSizes']),
            'usedCustoms' => array_values(array_unique($variantAnalysis['usedCustoms'])),
            'usedColorIds' => array_unique($variantAnalysis['usedColorIds']),
            'canEditSizeTable' => true,
            'canEditColors' => true,
            'canEditCustomTable' => true,
            'canCreateVariants' => true
        ];
    }

    /**
     * Ürün varyantlarını getirir
     */
    private function getProductVariants(Product $product): array
    {
        if (!$product->hasChildren()) {
            return [];
        }

        $variants = [];
        $productVariants = $product->getChildren([Product::OBJECT_TYPE_VARIANT]);

        foreach ($productVariants as $variant) {
            $variants[] = [
                'id' => $variant->getId(),
                'name' => $variant->getName(),
                'color' => $variant->getVariationColor()?->getColor(),
                'colorId' => $variant->getVariationColor()?->getId(),
                'size' => $variant->getVariationSize(),
                'custom' => $variant->getCustomField(),
            ];
        }

        return $variants;
    }

    /**
     * Varyantları analiz eder
     */
    private function analyzeVariants(array $variants): array
    {
        $colors = [];
        $usedSizes = [];
        $usedCustoms = [];
        $usedColorIds = [];

        foreach ($variants as $variant) {
            if ($variant['colorId']) {
                $usedColorIds[] = $variant['colorId'];
                $colors[] = [
                    'id' => $variant['colorId'],
                    'name' => $variant['color']
                ];
            }

            if ($variant['size']) {
                $usedSizes[] = $variant['size'];
            }

            if ($variant['custom']) {
                $usedCustoms[] = $variant['custom'];
            }
        }

        return [
            'colors' => $colors,
            'usedSizes' => $usedSizes,
            'usedCustoms' => $usedCustoms,
            'usedColorIds' => $usedColorIds
        ];
    }

    /**
     * Size table datasını build eder
     */
    private function buildSizeTableData(Product $product, array $usedSizes): array
    {
        $sizeTable = [];
        $sizeTableData = $product->getVariationSizeTable();

        if ($sizeTableData && is_array($sizeTableData)) {
            foreach ($sizeTableData as $row) {
                $beden = $row['beden'] ?? $row['label'] ?? '';
                $sizeTable[] = [
                    'beden' => $beden,
                    'en' => $row['en'] ?? $row['width'] ?? '',
                    'boy' => $row['boy'] ?? $row['length'] ?? '',
                    'yukseklik' => $row['yukseklik'] ?? $row['height'] ?? '',
                    'birim' => $row['birim'] ?? $row['unit'] ?? '',
                    'locked' => in_array($beden, $usedSizes, true)
                ];
            }
        }

        return $sizeTable;
    }

    /**
     * Custom table datasını build eder
     */
    private function buildCustomTableData(Product $product, array $usedCustoms): array
    {
        $customTableData = $product->getCustomFieldTable();
        
        if (!$customTableData || !is_array($customTableData)) {
            return [];
        }

        $customRows = [];
        $customTitle = '';

        foreach ($customTableData as $index => $row) {
            $deger = $row['deger'] ?? $row['value'] ?? '';
            
            if (!empty($deger) && $deger !== '[object Object]') {
                if ($index === 0) {
                    $customTitle = $deger;
                } else {
                    $customRows[] = [
                        'deger' => $deger,
                        'locked' => in_array($deger, $usedCustoms, true)
                    ];
                }
            }
        }

        return [
            'title' => $customTitle,
            'rows' => $customRows
        ];
    }

    /**
     * İlişki array'ini formatlar
     */
    private function formatRelationArray($items): array
    {
        if (!$items) {
            return [];
        }

        $result = [];
        $itemsArray = is_array($items) ? $items : [$items];

        foreach ($itemsArray as $item) {
            $result[] = [
                'id' => $item->getId(),
                'name' => $item->getKey()
            ];
        }

        return $result;
    }

    /**
     * Generic listing getirir
     */
    private function getGenericListing(string $listingClass, string $condition = "published = 1", ?int $page = null, ?int $limit = null): array
    {
        $listing = new $listingClass();
        $listing->setCondition($condition);
        
        if ($limit !== null && $page !== null) {
            $offset = ($page - 1) * $limit;
            $listing->setLimit($limit);
            $listing->setOffset($offset);
        }
        
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

    /**
     * Tek obje validasyonu
     */
    private function validateSingleObject(string $type, $id, array &$errors, string $displayName): ?object
    {
        if (empty($id)) {
            return null;
        }

        $intId = (int)(is_array($id) ? $id[0] : $id);
        $object = $this->getObjectById(self::CLASS_MAPPING[$type], $intId);
        
        if (!$object) {
            $errors[] = "{$displayName} ID {$intId} bulunamadı";
        }

        return $object;
    }

    /**
     * Çoklu obje validasyonu
     */
    private function validateMultipleObjects(string $type, array $ids, array &$errors, string $displayName): array
    {
        if (empty($ids)) {
            return [];
        }

        $objects = [];
        foreach ($ids as $id) {
            if (!empty($id)) {
                $intId = (int)(is_array($id) ? $id[0] : $id);
                $object = $this->getObjectById(self::CLASS_MAPPING[$type], $intId);
                
                if (!$object) {
                    $errors[] = "{$displayName} ID {$intId} bulunamadı";
                } else {
                    $objects[] = $object;
                }
            }
        }

        return $objects;
    }

    /**
     * Çoklu obje getirir
     */
    private function getMultipleObjects(string $type, array $ids): array
    {
        $objects = [];
        foreach ($ids as $id) {
            if (!empty($id)) {
                $intId = (int)(is_array($id) ? $id[0] : $id);
                $object = $this->getObjectById(self::CLASS_MAPPING[$type], $intId);
                if ($object) {
                    $objects[] = $object;
                }
            }
        }
        return $objects;
    }

    /**
     * ID ile obje getirir
     */
    private function getObjectById(string $className, int $id): ?object
    {
        if (!class_exists($className)) {
            return null;
        }

        try {
            return $className::getById($id);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Alan ile ürün bulur
     */
    private function findByField(string $field, mixed $value): Product|false
    {
        $list = new ProductListing();
        $list->setCondition("`$field` = ?", [$value]);
        $list->setUnpublished(true);
        $list->setLimit(1);
        
        return $list->current();
    }
}