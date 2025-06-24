<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Pimcore\Model\Document;
use Symfony\Component\HttpFoundation\Request;
use Pimcore\Model\DataObject\Product; 


class ProductController extends AbstractController
{
    #[Route('/', name: 'product')]
    public function index(): Response
    {
        return $this->render('product/product.html.twig');
    }

}