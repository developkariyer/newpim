<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Pimcore\Model\Document;
use Symfony\Component\HttpFoundation\Request;
use Pimcore\Model\DataObject\Product; 


class CatalogController extends AbstractController
{
    #[Route('/', name: 'catalog')]
    public function catalog(): Response
    {
        $products = $this->getProducts(20, 0);
        return $this->render('catalog/catalog.html.twig', [
            'products' => $products['products'],
            'total' => $products['total']
        ]);
    }

    private function getProducts($limit, $offset, $condition = "published = 1"): array
    {
        $productsListing = new Product\Listing();
        $productsListing->setLimit($limit);
        $productsListing->setOffset($offset);
        $productsListing->setOrderKey('name');
        $productsListing->setOrder('asc');
        $productsListing->setCondition($condition);
        $totalCount = $productsListing->getTotalCount();
        $products = $productsListing->load();
        $formattedProducts = [];
        foreach ($products as $product) {
            $formattedProducts[] = [
                'id' => $product->getId(),
                'name' => $product->getName(),
                'image' => $product->getImage(),
                'productIdentifier' => $product->getProductIdentifier(),
                'category' => $product->getCategory()
            ];
        }

        return [
            'products' => $formattedProducts,
            'total' => $totalCount,
        ];
    }

}