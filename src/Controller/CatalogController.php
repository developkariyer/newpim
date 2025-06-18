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
        $productsListing = new Product\Listing();
        $productsListing->setOrderKey('name'); 
        $productsListing->setOrder('ASC');    
        $products = $productsListing->getObjects();
        return $this->render('catalog/catalog.html.twig', [
            'products' => $products,
        ]);
    }

}