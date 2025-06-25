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

class ProductController extends AbstractController
{
    #[Route('/product', name: 'product')]
    public function index(): Response
    {
        $categories = $this->getCategories();
        $sizeCharts = $this->getSizeCharts();
        $colors = $this->getColors();
        $customCharts = $this->getCustomCharts();
        return $this->render('product/product.html.twig', [
            'categories' => $categories,
            'sizeCharts' => $sizeCharts,
            'colors' => $colors,
            'customCharts' => $customCharts
        ]);
    }

    private function getSizeCharts()
    {
        /*
         * This method retrieves all size charts that are published.
         * It returns an array of size charts with their ID and name.
         */
        $sizeCharts = new VariationSizeChartListing();
        $sizeCharts->setCondition("published = 1");
        $sizeCharts->load();
        $sizeChartList = [];
        foreach ($sizeCharts as $sizeChart) {
            $sizeChartList[] = [
                'id' => $sizeChart->getId(),
                'name' => $sizeChart->getKey(),
            ];
        }
        return $sizeChartList;
    }

    private function getCustomCharts()
    {
        /*
         * This method retrieves all custom charts that are published.
         * It returns an array of custom charts with their ID and name.
         */
        $customCharts = new CustomChartListing();
        $customCharts->setCondition("published = 1");
        $customCharts->load();
        $customChartList = [];
        foreach ($customCharts as $customChart) {
            $customChartList[] = [
                'id' => $customChart->getId(),
                'name' => $customChart->getKey(),
            ];
        }
        return $customChartList;
    }

    private function getCategories()
    {
        /*
         * This method retrieves all categories that are published and do not have children.
         * It returns an array of categories with their ID and name.
         */
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

    private function getColors()
    {
        /*
         * This method retrieves all colors that are published.
         * It returns an array of colors with their ID and name.
         */
        $colors = new VariationColorListing();
        $colors->setCondition("published = 1");
        $colors->load();
        $colorList = [];
        foreach ($colors as $color) {
            $colorList[] = [
                'id' => $color->getId(),
                'name' => $color->getKey(),
            ];
        }
        return $colorList;
    }

}