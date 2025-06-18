<?php

namespace App\Controller;

use Pimcore\Model\DataObject\Product;
use Pimcore\Model\DataObject\Category; 
use Pimcore\Paginator\Adapter\DataObjectListing; 
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request; 
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Zend\Paginator\Paginator; 

class CatalogController extends AbstractController
{

    #[Route('/', name: 'catalog')]
    public function catalog(Request $request): Response
    {
        $page = (int) $request->query->get('page', 1);
        $categoryId = (int) $request->query->get('category');
        $searchTerm = $request->query->get('search');

        $productsListing = new Product\Listing();

        if ($categoryId) {
            $productsListing->setCondition('categories LIKE ?', ["%,$categoryId,%"]);
        }

        if ($searchTerm) {
            $productsListing->setCondition(
                '(name LIKE ? OR description LIKE ?)', 
                ['%' . $searchTerm . '%', '%' . $searchTerm . '%']
            );
        }
        $productsListing->setOrderKey('name');
        $productsListing->setOrder('ASC');
        $paginatorAdapter = new DataObjectListing($productsListing);
        $paginator = new Paginator($paginatorAdapter);
        $paginator->setItemCountPerPage(12);
        $paginator->setCurrentPageNumber($page);
        $categories = (new Category\Listing())->getObjects();
        
        return $this->render('catalog/catalog.html.twig', [
            'paginator' => $paginator, 
            'categories' => $categories, 
            'currentCategoryId' => $categoryId, 
            'currentSearchTerm' => $searchTerm, 
        ]);
    }
}