<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Pimcore\Model\Document;
use Symfony\Component\HttpFoundation\Request;
use Pimcore\Model\DataObject\Product; 
use Pimcore\Model\DataObject\Brand;
use Pimcore\Model\DataObject\Category;
use Pimcore\Model\DataObject\Marketplace;
use Pimcore\Model\DataObject\Color;
use Pimcore\Model\DataObject\Category\Listing as CategoryListing;
use Pimcore\Model\DataObject\Color\Listing as VariationColorListing;
use Pimcore\Model\DataObject\Brand\Listing as BrandListing;
use Pimcore\Model\DataObject\Marketplace\Listing as MarketplaceListing;
use Pimcore\Model\DataObject\Product\Listing as ProductListing;


class ProductController extends AbstractController
{
    private const TYPE_MAPPING = [
        'colors' => VariationColorListing::class,
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
        $categories = $this->getCategories();
        $colors = $this->getGenericListing(self::TYPE_MAPPING['colors']);
        $brands = $this->getGenericListing(self::TYPE_MAPPING['brands']);
        $marketplaces = $this->getGenericListing(self::TYPE_MAPPING['marketplaces']);
        return $this->render('product/product.html.twig', [
            'categories' => $categories,
            'colors' => $colors,
            'brands' => $brands,
            'marketplaces' => $marketplaces
        ]);
    }

    #[Route('/product/search/{type}', name: 'product_search', methods: ['GET'])]
    public function search(Request $request, string $type): JsonResponse
    {
        $query = trim($request->query->get('q', ''));
        if (!isset(self::TYPE_MAPPING[$type])) {
            return new JsonResponse(['error' => 'Invalid search type'], 400);
        }
        // if (strlen($query) < 2) {
        //     return new JsonResponse([
        //         'items' => []
        //     ]);
        // }
        $escapedQuery = addslashes($query);
        $searchCondition = "published = 1 AND LOWER(`key`) LIKE LOWER('%{$escapedQuery}%')";
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
            
            $listing = new ProductListing();
            $listing->setCondition('productIdentifier LIKE ? OR name LIKE ?', ["%$query%", "%$query%"]);
            $listing->setLimit(1); 
            $products = $listing->load();
            $items = [];
            
            foreach ($products as $product) {
                $variants = [];
                $variantColors = [];
                $hasVariants = $product->hasChildren();
                
                if ($hasVariants) {
                    $variantListing = new ProductListing();
                    $variantListing->setCondition('o_parentId = ?', [$product->getId()]);
                    $variantListing->load();
                    foreach ($variantListing as $variant) {
                        $variants[] = [
                            'id' => $variant->getId(),
                            'name' => $variant->getName(),
                            'color' => $variant->getVariationColor() ? $variant->getVariationColor()->getColor() : null,
                            'colorId' => $variant->getVariationColor() ? $variant->getVariationColor()->getId() : null,
                            'size' => $variant->getVariationSize(),
                            'custom' => $variant->getCustomField(),
                        ];
                        if ($variant->getVariationColor()) {
                            $variantColors[] = [
                                'id' => $variant->getVariationColor()->getId(),
                                'name' => $variant->getVariationColor()->getColor()
                            ];
                        }
                    }
                }
                
                $brands = [];
                if ($product->getBrandItems()) {
                    foreach ($product->getBrandItems() as $brand) {
                        $brands[] = [
                            'id' => $brand->getId(),
                            'name' => $brand->getKey()
                        ];
                    }
                }
                
                $marketplaces = [];
                if ($product->getMarketplaces()) {
                    foreach ($product->getMarketplaces() as $marketplace) {
                        $marketplaces[] = [
                            'id' => $marketplace->getId(),
                            'name' => $marketplace->getKey()
                        ];
                    }
                }
                
                $sizeTable = $product->getVariationSizeTable() ?: [];
                $customTable = $product->getCustomFieldTable() ?: [];
                
                $items[] = [
                    'id' => $product->getId(),
                    'name' => $product->getName(),
                    'productIdentifier' => $product->getProductIdentifier(),
                    'description' => $product->getDescription(),
                    'categoryId' => $product->getProductCategory() ? $product->getProductCategory()->getId() : null,
                    'categoryName' => $product->getProductCategory() ? $product->getProductCategory()->getKey() : null,
                    'brandIds' => array_map(fn($brand) => $brand->getId(), $product->getBrandItems() ?: []),
                    'brands' => $brands,
                    'marketplaceIds' => array_map(fn($mp) => $mp->getId(), $product->getMarketplaces() ?: []),
                    'marketplaces' => $marketplaces,
                    'imagePath' => $product->getImage() ? $product->getImage()->getFullPath() : null,
                    'hasVariants' => $hasVariants,
                    'variants' => $variants,
                    'variantColors' => array_unique($variantColors, SORT_REGULAR), 
                    'sizeTable' => $sizeTable, 
                    'customTable' => $customTable, 
                    'canEditSizeTable' => !$hasVariants, 
                    'canEditColors' => true, 
                    'canEditCustomTable' => !$hasVariants, 
                ];
            }
            
            return new JsonResponse(['items' => $items]);
            
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/product/create', name: 'product_create', methods: ['POST'])]
    public function create(Request $request): Response
    {
        $productName = $request->get('productName');
        $productIdentifier = $request->get('productIdentifier');
        $productDescription = $request->get('productDescription');
        $imageFile = $request->files->get('productImage');
        $categoryId = $request->get('productCategory');
        $brandIds = $request->get('brands', []);
        $marketplaceIds = $request->get('marketplaces', []);
        $colorIds = $request->get('colors', []);
        $customTemplateId = $request->get('customTemplate');
        $sizeTableData = $request->get('sizeTableData');
        $customTableData = $request->get('customTableData');
        $variations = $request->get('variationsData');
        if ($variations) {
            $variations = json_decode($variations, true);
        }
        $errors = [];
        $category = $this->validateSingleObject('category', $categoryId, $errors, 'Kategori');
        $brands = $this->validateMultipleObjects('brand', $brandIds, $errors, 'Marka');
        $marketplaces = $this->validateMultipleObjects('marketplace', $marketplaceIds, $errors, 'Pazaryeri');
        $colors = $this->validateMultipleObjects('color', $colorIds, $errors, 'Renk');
        if (!empty($errors)) {
            return $this->render('product/product.html.twig', [
                'errors' => $errors
            ]);
        }

        $imageAsset = null;
        if ($imageFile && $imageFile->isValid()) {
            $imageAsset = $this->uploadProductImage($imageFile, $productIdentifier ?: $productName);
        }

        $mainFolderId = 1246;
        $identifierPrefix = strtoupper(explode('-', $productIdentifier)[0]);
        $productsFolder = \Pimcore\Model\DataObject\Folder::getById($mainFolderId);
        $parentFolderPath = $productsFolder->getFullPath() . '/' . $identifierPrefix;
        $parentFolder = \Pimcore\Model\DataObject\Folder::getByPath($parentFolderPath);
        if (!$parentFolder) {
            $parentFolder = new \Pimcore\Model\DataObject\Folder();
            $parentFolder->setKey($identifierPrefix);
            $parentFolder->setParent($productsFolder);
            $parentFolder->save();
        }
        try {   
            $product = new Product();
            $product->setParent($parentFolder);
            $product->setKey($productIdentifier . ' ' . $productName);
            $product->setName($productName);
            $product->setProductIdentifier($productIdentifier);
            $product->setDescription($productDescription);
            $product->setProductCategory($category);
            $product->setBrandItems($brands);
            $product->setMarketplaces($marketplaces);
            if ($imageAsset) {
                $product->setImage($imageAsset);
            }
            $variationSizeTable = [];
            if ($sizeTableData) {
                $variationSizeTable = json_decode($sizeTableData, true);
                if (!is_array($variationSizeTable)) {
                    $variationSizeTable = [];
                }
                $product->setVariationSizeTable($variationSizeTable);
            }
            $customFieldTable = [];
            if ($customTableData) {
                $customTableDecoded = json_decode($customTableData, true);
                if (
                    is_array($customTableDecoded)
                    && isset($customTableDecoded['rows'])
                    && is_array($customTableDecoded['rows'])
                ) {
                    $title = isset($customTableDecoded['title']) ? $customTableDecoded['title'] : '';
                    if ($title !== '') {
                        array_unshift($customTableDecoded['rows'], ['deger' => $title, 'isTitle' => true]);
                    }
                    $customFieldTable = $customTableDecoded['rows'];
                } elseif (is_array($customTableDecoded)) {
                    $customFieldTable = $customTableDecoded;
                }
                $product->setCustomFieldTable($customFieldTable);
            }
            $this->checkProductCode($product);
            $product->setPublished(true);
            $product->save();
            dump('Variations:', $variations);
            if (is_array($variations) && count($variations) > 0) {
                $this->addProductVariants($product, $variations);
            }
            $this->addFlash('success', 'Ürün ve varyantlar başarıyla oluşturuldu.');
            return $this->redirectToRoute('product');
        }catch (\Throwable $e) {
            $this->addFlash('danger', 'Ürün oluşturulurken bir hata oluştu: ' . $e->getMessage());
            return $this->render('product/product.html.twig', [
                'errors' => ['Ürün oluşturulurken bir hata oluştu. Lütfen tekrar deneyin.'],
            ]);
        }
        
    }

    #[Route('/product/update', name: 'product_update', methods: ['POST'])]
    public function update(Request $request): Response
    {
        $editingProductId = $request->get('editingProductId');
        if (!$editingProductId) {
            $this->addFlash('danger', 'Güncellenecek ürün ID\'si bulunamadı.');
            return $this->redirectToRoute('product');
        }
        
        $product = Product::getById($editingProductId);
        if (!$product) {
            $this->addFlash('danger', 'Güncellenecek ürün bulunamadı.');
            return $this->redirectToRoute('product');
        }
        
        try {
            // Sadece izin verilen alanları güncelle
            $productName = $request->get('productName');
            $productDescription = $request->get('productDescription');
            $imageFile = $request->files->get('productImage');
            $categoryId = $request->get('productCategory');
            $brandIds = $request->get('brands', []);
            $marketplaceIds = $request->get('marketplaces', []);
            $colorIds = $request->get('colors', []);
            
            // Validation
            $errors = [];
            $category = $this->validateSingleObject('category', $categoryId, $errors, 'Kategori');
            $brands = $this->validateMultipleObjects('brand', $brandIds, $errors, 'Marka');
            $marketplaces = $this->validateMultipleObjects('marketplace', $marketplaceIds, $errors, 'Pazaryeri');
            $colors = $this->validateMultipleObjects('color', $colorIds, $errors, 'Renk');
            
            if (!empty($errors)) {
                foreach ($errors as $error) {
                    $this->addFlash('danger', $error);
                }
                return $this->redirectToRoute('product');
            }
            
            // Ürün bilgilerini güncelle
            if ($productName) {
                $product->setName($productName);
            }
            if ($productDescription) {
                $product->setDescription($productDescription);
            }
            
            // Kategori güncelle
            if ($category) {
                $product->setProductCategory($category);
            }
            
            // Markalar güncelle
            if (!empty($brands)) {
                $product->setBrandItems($brands);
            }
            
            // Pazaryerleri güncelle
            if (!empty($marketplaces)) {
                $product->setMarketplaces($marketplaces);
            }
            
            // Renkler güncelle
            if (!empty($colors)) {
                $product->setVariationColor($colors);
            }
            
            // Resim güncelle (eğer yeni resim yüklendiyse)
            if ($imageFile && $imageFile->isValid()) {
                $imageAsset = $this->uploadProductImage($imageFile, $product->getProductIdentifier());
                if ($imageAsset) {
                    $product->setImage($imageAsset);
                }
            }
            
            // Tablolar güncelle
            $sizeTableData = $request->get('sizeTableData');
            if ($sizeTableData) {
                $variationSizeTable = json_decode($sizeTableData, true);
                if (is_array($variationSizeTable)) {
                    $product->setVariationSizeTable($variationSizeTable);
                }
            }
            
            $customTableData = $request->get('customTableData');
            if ($customTableData) {
                $customFieldTable = json_decode($customTableData, true);
                if (is_array($customFieldTable)) {
                    $product->setCustomFieldTable($customFieldTable);
                }
            }
            
            // Yeni varyantlar ekle (eğer varsa ve daha önce varyant yoksa)
            $variations = $request->get('variationsData');
            if ($variations && !$product->hasChildren()) {
                $variations = json_decode($variations, true);
                if (is_array($variations) && count($variations) > 0) {
                    $this->addProductVariants($product, $variations);
                }
            }
            
            $product->save();
            
            $this->addFlash('success', 'Ürün başarıyla güncellendi.');
            return $this->redirectToRoute('product');
            
        } catch (\Throwable $e) {
            $this->addFlash('danger', 'Ürün güncellenirken bir hata oluştu: ' . $e->getMessage());
            return $this->redirectToRoute('product');
        }
    }

    private function addProductVariants(Product $parentProduct, array $variations): void
    {
        foreach ($variations as $variantData) {
            $variant = new Product();
            $variant->setParent($parentProduct);
            $variant->setType(Product::OBJECT_TYPE_VARIANT);
            $parentIdentifier = $parentProduct->getProductIdentifier();
            $parentName = $parentProduct->getName();
            $variantKey = implode('-', array_filter([$variantData['renk'] ?? '', $variantData['beden'] ?? '', $variantData['custom'] ?? '']));
            $fullKeyBase = $parentIdentifier . '-' . $parentName . '-' . ($variantKey ?: 'variant');
            $variant->setKey($fullKeyBase);
            $variant->setName($fullKeyBase);
            if (!empty($variantData['renk'])) {
                $colorObj = new \Pimcore\Model\DataObject\Color\Listing();
                $colorObj->setCondition('color = ?', [$variantData['renk']]);
                $colorObj->setLimit(1);
                $colorObj = $colorObj->current();
                if ($colorObj) {
                    $variant->setVariationColor($colorObj);
                }
            }
            if (!empty($variantData['beden'])) {
                $variant->setVariationSize($variantData['beden']);
            }
            if (!empty($variantData['custom'])) {
                $variant->setCustomField($variantData['custom']);
            }
            $this->checkIwasku($parentIdentifier , $variant);
            $variant->setPublished(true);
            $variant->save();
            dump('Varyant kaydedildi:', $variant->getId());
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

        $existing = new Color\Listing();
        $existing->setCondition('color = ?', [$colorName]);
        if ($existing->count() > 0) {
            return new JsonResponse(['success' => false, 'message' => 'Bu renk zaten mevcut.']);
        }

        $color = new Color();
        $color->setKey($colorName);
        $color->setParentId(1247);
        $color->setColor($colorName);
        $color->setPublished(true);
        $color->save();

        return new JsonResponse(['success' => true, 'id' => $color->getId()]);
    }

    public function checkIwasku($parentIdentifier, $product): bool
    {
        if (
            $product->getType() == Product::OBJECT_TYPE_VARIANT
            && strlen($product->getIwasku() ?? '') != 12
        ) {
            $iwasku = str_pad(str_replace('-', '', $parentIdentifier), 7, '0', STR_PAD_RIGHT);
            $productCode = $this->generateUniqueCode(5);
            $product->setProductCode($productCode);
            $iwasku .= $productCode;
            $product->setIwasku($iwasku);
            return true;
        }
        return false;
    }

    private function checkProductCode($product, $numberDigits = 5): bool
    {
        Product::setGetInheritedValues(false);
        if (strlen($product->getProductCode()) == $numberDigits) {
            Product::setGetInheritedValues(true);
            return false;
        }
        $productCode = $this->generateUniqueCode($numberDigits);
        $product->setProductCode($productCode);
        Product::setGetInheritedValues(true);
        return true;
    }

    private function generateUniqueCode(int $numberDigits=5): string
    {
        while (true) {
            $candidateCode = $this->generateCustomString($numberDigits);
            if (!$this->findByField('productCode', $candidateCode)) {
                return $candidateCode;
            }
        }
    }

    private static function generateCustomString(int $length = 5): string
    {
        $characters = 'ABCDEFGHJKMNPQRSTVWXYZ1234567890';
        $charactersLength = strlen($characters);
        $randomString = '';

        for ($i = 0; $i < $length; $i++) {
            $randomIndex = mt_rand(0, $charactersLength - 1);
            $randomString .= $characters[$randomIndex];
        }
        return $randomString;
    }

    public function findByField(string $field, mixed $value): \Pimcore\Model\DataObject\Product|false
    {
        $list = new ProductListing();
        $list->setCondition("`$field` = ?", [$value]);
        $list->setUnpublished(true);
        $list->setLimit(1);
        return $list->current();
    }

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

    private function validateSingleObject(string $type, $id, array &$errors, string $displayName): ?object
    {
        if (empty($id)) {
            return null;
        }
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
        if (empty($ids)) {
            return [];
        }
        $objects = [];
        foreach ($ids as $id) {
            if (!empty($id)) {
                $idValue = is_array($id) ? $id[0] : $id;
                $intId = (int)$idValue;
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

    private function getObjectById(string $className, int $id): ?object
    {
        if (!class_exists($className)) {
            return null;
        }
        try {
            return $className::getById($id);
        } catch (\Exception $e) {
            return null;
        }
    }

    private function getCategories()
    {
        $categories = new CategoryListing();
        $categories->setCondition("published = 1");
        $categories->load();
        $categoryList = [];
        foreach ($categories as $category) {
            if ($category->hasChildren()) {
                continue; 
            }
            $categoryList[] = [
                'id' => $category->getId(),
                'name' => $category->getKey(),
            ];
        }
        return $categoryList;
    }

    private function uploadProductImage($imageFile, string $productKey): ?\Pimcore\Model\Asset\Image
    {
        try {
            dump('uploadProductImage başlıyor...');
            dump('File info:', [
                'name' => $imageFile->getClientOriginalName(),
                'size' => $imageFile->getSize(),
                'mime' => $imageFile->getMimeType(),
                'temp_path' => $imageFile->getPathname()
            ]);
            $assetFolder = \Pimcore\Model\Asset::getByPath('/products');
            if (!$assetFolder) {
                dump('Products klasörü yok, oluşturuluyor...');
                $assetFolder = new \Pimcore\Model\Asset\Folder();
                $assetFolder->setFilename('products');
                $assetFolder->setParent(\Pimcore\Model\Asset::getByPath('/'));
                $assetFolder->save();
                dump('Products klasörü oluşturuldu:', $assetFolder->getFullPath());
            } else {
                dump('Products klasörü mevcut:', $assetFolder->getFullPath());
            }
            $extension = $imageFile->getClientOriginalExtension() ?: 'jpg';
            $filename = $this->generateSafeFilename($productKey) . '_' . time() . '.' . $extension;
            dump('Generated filename:', $filename);
            $imageAsset = new \Pimcore\Model\Asset\Image();
            $imageAsset->setFilename($filename);
            $imageAsset->setParent($assetFolder);
            $fileContent = file_get_contents($imageFile->getPathname());
            if ($fileContent === false) {
                throw new \Exception('Dosya içeriği okunamadı');
            }
            $imageAsset->setData($fileContent);
            $imageAsset->save();
            dump('Image asset oluşturuldu:', [
                'ID' => $imageAsset->getId(),
                'Path' => $imageAsset->getFullPath(),
                'Size' => $imageAsset->getFileSize()
            ]);
            return $imageAsset;
        } catch (\Exception $e) {
            dump('Image upload HATA:', $e->getMessage());
            return null;
        }
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

}