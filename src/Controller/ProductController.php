<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Pimcore\Model\Document;
use Symfony\Component\HttpFoundation\Request;
use Pimcore\Model\DataObject\Product; 
use Pimcore\Model\DataObject\Category\Listing as CategoryListing;
use Pimcore\Model\DataObject\VariationSizeChart\Listing as VariationSizeChartListing;
use Pimcore\Model\DataObject\VariationColor\Listing as VariationColorListing;
use Pimcore\Model\DataObject\CustomChart\Listing as CustomChartListing;
use Pimcore\Model\DataObject\Brand\Listing as BrandListing;
use Pimcore\Model\DataObject\Marketplace\Listing as MarketplaceListing;

class ProductController extends AbstractController
{
    #[Route('/product', name: 'product')]
    public function index(): Response
    {
        $categories = $this->getCategories();
        $sizeCharts = $this->getGenericListing(VariationSizeChartListing::class);
        $colors = $this->getGenericListing(VariationColorListing::class);
        $customCharts = $this->getGenericListing(CustomChartListing::class);
        $brands = $this->getGenericListing(BrandListing::class);
        $marketplaces = $this->getGenericListing(MarketplaceListing::class);
        return $this->render('product/product.html.twig', [
            'categories' => $categories,
            'sizeCharts' => $sizeCharts,
            'colors' => $colors,
            'customCharts' => $customCharts,
            'brands' => $brands,
            'marketplaces' => $marketplaces
        ]);
    }

    private function getGenericListing(string $listingClass, string $condition = "published = 1", ?int $page = null, ?int $limit = null): array 
    {
        $listing = new $listingClass();
        $listing->setCondition($condition);
        if ($limit !== null) {
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