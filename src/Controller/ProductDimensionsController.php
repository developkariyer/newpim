<?php
namespace App\Controller;

use App\Connector\Marketplace\CiceksepetiConnector;
use App\Model\DataObject\Marketplace;
use App\Model\DataObject\VariantProduct;
use App\Utils\Utility;
use Doctrine\DBAL\Exception;
use Pimcore\Db;
use Random\RandomException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Message\CiceksepetiCategoryUpdateMessage;
use Symfony\Component\Messenger\MessageBusInterface;
use Pimcore\Controller\FrontendController;
use Pimcore\Model\DataObject\Product;
use Pimcore\Model\DataObject\Data\Link;
use Pimcore\Model\DataObject\Category;
use Pimcore\Model\Asset;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\ExpressionLanguage\Expression;

#[IsGranted(new Expression("is_granted('ROLE_PRODUCTDIMENSIONSMANAGER') or is_granted('ROLE_PIMCORE_ADMIN')"))]
class ProductDimensionsController extends FrontendController
{
    /**
     * @Route("/productDimensions", name="product_dimensions_main_page")
     * @param Request $request
     * @return Response
     */
    public function productDimensionsMainPage(Request $request): Response
    {
        $pageSize = 50;
        $offset = 0;
        $iwasku = $request->query->get('iwasku');
        $category = $request->query->get('category');
        $page = max(1, (int) $request->query->get('page', 1));
        $offset = ($page - 1) * $pageSize;
        $listingObject = new Product\Listing();
        $listingObject->setUnpublished(false);
        $conditions = "iwasku IS NOT NULL AND iwasku != ''";
        if ($iwasku) {
            $conditions .= " AND iwasku LIKE '%" . $iwasku . "%'";
        }
        if ($category) {
            try {
                $categoryId = $this->findCategoryIdByName($category);
                if ($categoryId) {
                    $conditions .= " AND productCategory = " . $categoryId;
                } else {
                    $conditions .= " AND productCategory = -1";
                }
            } catch (\Exception $e) {
                error_log('Category search error: ' . $e->getMessage());
            }
        }
        $packageStatus = $request->query->get('packageStatus');
        if ($packageStatus === 'with-dimensions') {
            $conditions .= " AND packageDimension1 IS NOT NULL AND packageDimension2 IS NOT NULL AND packageDimension3 IS NOT NULL";
        } elseif ($packageStatus === 'without-dimensions') {
            $conditions .= " AND (packageDimension1 IS NULL OR packageDimension2 IS NULL OR packageDimension3 IS NULL)";
        }
        $search = $request->query->get('search');
        if ($search) {
            $conditions .= " AND (name LIKE '%" . $search . "%' OR iwasku LIKE '%" . $search . "%' OR variationSize LIKE '%" . $search . "%' OR variationColor LIKE '%" . $search . "%')";
        }
        $listingObject->setCondition($conditions);
        $listingObject->setLimit($pageSize);
        $listingObject->setOffset($offset);
        $products = $listingObject->load();
        $count = $listingObject->count();
        $productData = [];
        foreach ($products as $product) {
            if ($product->getType() !== 'variant' || !$product instanceof Product) {
                continue;
            }
            $catagoryData = $product->getProductCategory();
            if (!$catagoryData || !$catagoryData instanceof Category) {
                continue;
            }
            $categoryPath = $catagoryData->getCategory();
            $categoryName = !empty($categoryPath) ? basename($categoryPath) : '';


            $productData[] = [
                'id' => $product->getId(),
                'name' => $product->getName(),
                'iwasku' => $product->getIwasku(),
                'variationSize' => $product->getVariationSize(),
                'variationColor' => $product->getVariationColor(),
                'wsCategory' => $categoryName,
                'weight' => $product->getPackageWeight(),
                'width' => $product->getPackageDimension1(),
                'length' => $product->getPackageDimension2(),
                'height' => $product->getPackageDimension3(),
                'desi5000' => $product->getDesi5000()
            ];
        }
        $categories = [];
        try {
            $categoryListing = new Category\Listing();
            $categoryListing->setCondition("published = 1 AND category IS NOT NULL AND category != ''");
            $categoryListing->setOrderKey("category");
            $categoryListing->setOrder("ASC");
            $categoryObjects = $categoryListing->load();
            $categoryNames = [];
            foreach ($categoryObjects as $categoryObject) {
                if ($categoryObject instanceof Category && $categoryObject->getCategory()) {
                    $categoryName = basename($categoryObject->getCategory());
                    if (!empty($categoryName)) {
                        $categoryNames[$categoryName] = true; 
                    }
                }
            }
            $categories = array_keys($categoryNames);
            sort($categories);
            
        } catch (\Exception $e) {
            error_log('Category listing error: ' . $e->getMessage());
            $categories = [];
        }
        return $this->render('productDimensions/productDimensions.html.twig', [
            'products' => $productData,
            'total' => $count,
            'page' => $page,
            'pageSize' => $pageSize,
            'categories' => $categories
        ]);
    }

    /**
     * @Route("/api/updateProductDimensions", name="product_dimensions_update", methods={"POST"})
     * @param Request $request
     * @return Response
     */
    public function updateProductDimensions(Request $request): Response
    {
        $data = json_decode($request->getContent(), true);
        $productId = $data['id'] ?? null;
        $success = false;
        $message = '';
        if (!$productId) {
            return $this->json([
                'success' => false,
                'message' => 'Ürün Bulunamadı.'
            ]);
        }
        try {
            $product = Product::getById($productId);
            if (!$product || !$product instanceof Product) {
                return $this->json([
                    'success' => false,
                    'message' => 'Ürün bulunamadı.'
                ]);
            }
            $weight = $data['weight'] ?? null;
            $width = $data['width'] ?? null;
            $length = $data['length'] ?? null;
            $height = $data['height'] ?? null;
            if ($weight !== null) {
                $product->setPackageWeight($weight);
            }

            if ($width !== null) {
                $product->setPackageDimension1($width);
            }

            if ($length !== null) {
                $product->setPackageDimension2($length);
            }

            if ($height !== null) {
                $product->setPackageDimension3($height);
            }
            $product->save();
            $success = true;
            $message = 'Ürün başarıyla güncellendi.';
        } catch (\Exception $e) {
            $message = 'Hata oluştu: ' . $e->getMessage();
        }
        return $this->json([
            'success' => $success,
            'message' => $message
        ]);
    }

    private function findCategoryIdByName(string $categoryName): ?int
    {
        try {
            $categoryListing = new Category\Listing();
            $categoryListing->setCondition(
                "published = 1 AND (category = '" . $categoryName . "' OR category LIKE '%/" . $categoryName . "')"
            );
            $categoryListing->setLimit(1);
            $categoryObjects = $categoryListing->load();
            return !empty($categoryObjects) ? $categoryObjects[0]->getId() : null;
        } catch (\Exception $e) {
            error_log('Find category ID error: ' . $e->getMessage());
            return null;
        }
    }
}