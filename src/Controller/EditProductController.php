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



class EditProductController extends AbstractController
{

    
    #[Route('/product-edit', name: 'productEdit')]
    public function index(Request $request): Response
    {
        $products = null;
        $q = trim($request->query->get('q', ''));
        if ($q !== '') {
            $listing = new ProductListing();
            $listing->setCondition('productIdentifier LIKE ?', ['%' . $q . '%']);
            $listing->setLimit(20);
            $products = iterator_to_array($listing);
        }

        return $this->render('product/edit.html.twig', [
            'products' => $products,
            'q' => $q,
        ]);
    }

}