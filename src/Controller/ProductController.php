<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Pimcore\Model\Document;
use Symfony\Component\HttpFoundation\Request;
use Pimcore\Model\DataObject\Product; 
use Pimcore\Model\DataObject\Category\Listing as CategoryListing;


class ProductController extends AbstractController
{
    #[Route('/product', name: 'product')]
    public function index(): Response
    {
        $categories = $this->getCategories();
        return $this->render('product/product.html.twig', [
            'categories' => $categories
        ]);
    }

    private function getCategories()
    {
        $categories = new CategoryListing();
        $categories->load();
        $categoryList = [];
        foreach ($categories as $category) {
            $categoryList[] = [
                'id' => $category->getId(),
                'name' => $category->getCategory(),
            ];
        }
    }

}