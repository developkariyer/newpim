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
use Pimcore\Model\DataObject\CustomChart;
use Pimcore\Model\DataObject\VariationColor;
use Pimcore\Model\DataObject\VariationSizeChart;
use Pimcore\Model\DataObject\Category\Listing as CategoryListing;
use Pimcore\Model\DataObject\VariationSizeChart\Listing as VariationSizeChartListing;
use Pimcore\Model\DataObject\VariationColor\Listing as VariationColorListing;
use Pimcore\Model\DataObject\CustomChart\Listing as CustomChartListing;
use Pimcore\Model\DataObject\Brand\Listing as BrandListing;
use Pimcore\Model\DataObject\Marketplace\Listing as MarketplaceListing;
use Pimcore\Model\DataObject\Product\Listing as ProductListing;


class ProductController extends AbstractController
{
    private const TYPE_MAPPING = [
        'colors' => VariationColorListing::class,
        'brands' => BrandListing::class,
        'marketplaces' => MarketplaceListing::class,
        'customCharts' => CustomChartListing::class,
        'sizeCharts' => VariationSizeChartListing::class,
        'categories' => CategoryListing::class,
        'products' => ProductListing::class
    ];

    private const CLASS_MAPPING = [
        'category' => Category::class,
        'brand' => Brand::class,
        'marketplace' => Marketplace::class,
        'product' => Product::class,
        'color' => VariationColor::class,
        'sizeChart' => VariationSizeChart::class,
        'customChart' => CustomChart::class,
    ];
    
    #[Route('/product', name: 'product')]
    public function index(): Response
    {
        $categories = $this->getCategories();
        $sizeCharts = $this->getGenericListing(self::TYPE_MAPPING['sizeCharts']);
        $colors = $this->getGenericListing(self::TYPE_MAPPING['colors']);
        $customCharts = $this->getGenericListing(self::TYPE_MAPPING['customCharts']);
        $brands = $this->getGenericListing(self::TYPE_MAPPING['brands']);
        $marketplaces = $this->getGenericListing(self::TYPE_MAPPING['marketplaces']);
        $products = $this->getGenericListing(self::TYPE_MAPPING['products']);
        return $this->render('product/product.html.twig', [
            'categories' => $categories,
            'sizeCharts' => $sizeCharts,
            'colors' => $colors,
            'customCharts' => $customCharts,
            'brands' => $brands,
            'marketplaces' => $marketplaces,
            'products' => $products
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
        $sizeTemplateId = $request->get('sizeTemplate');
        $colorIds = $request->get('colorTemplate', []);
        $customTemplateId = $request->get('customTemplate');
        $setProducts = $request->get('products', []);
        
        $category = !empty($categoryId) ? $this->getObjectById(self::CLASS_MAPPING['category'], (int)$categoryId) : null;
        $brands = !empty($brandIds) ? $this->getObjectsByIds(self::CLASS_MAPPING['brand'], $brandIds) : [];
        $marketplaces = !empty($marketplaceIds) ? $this->getObjectsByIds(self::CLASS_MAPPING['marketplace'], $marketplaceIds) : [];
        $sizeChart = !empty($sizeTemplateId) ? $this->getObjectById(self::CLASS_MAPPING['sizeChart'], (int)$sizeTemplateId) : null;
        $colors = !empty($colorIds) ? $this->getObjectsByIds(self::CLASS_MAPPING['color'], $colorIds) : [];
        $customChart = !empty($customTemplateId) ? $this->getObjectById(self::CLASS_MAPPING['customChart'], (int)$customTemplateId) : null;
        $setProductObjects = !empty($setProducts) ? $this->getObjectsByIds(self::CLASS_MAPPING['product'], array_keys($setProducts)) : [];
        
        dump([
            'Form Verileri' => [
                'productName' => $productName,
                'productIdentifier' => $productIdentifier,
                'productDescription' => $productDescription,
                'hasImage' => $imageFile ? 'Evet' : 'Hayır'
            ],
            'Bulunan Objeler' => [
                'category' => $category ? $category->getKey() : 'Seçilmedi',
                'brands' => array_map(fn($b) => $b->getKey(), $brands),
                'marketplaces' => array_map(fn($m) => $m->getKey(), $marketplaces),
                'sizeChart' => $sizeChart ? $sizeChart->getKey() : 'Seçilmedi',
                'colors' => array_map(fn($c) => $c->getKey(), $colors),
                'customChart' => $customChart ? $customChart->getKey() : 'Seçilmedi',
                'setProducts' => array_map(fn($p) => $p->getKey(), $setProductObjects),
            ],
            'Set Ürün Miktarları' => $setProducts
        ]);


        return $this->render('product/product.html.twig');
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

    private function getObjectsByIds(string $className, array $ids): array
    {
        $objects = [];
        foreach ($ids as $id) {
            $object = $this->getObjectById($className, (int)$id);
            if ($object && $object->getPublished()) {
                $objects[] = $object;
            }
        }
        return $objects;
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

}