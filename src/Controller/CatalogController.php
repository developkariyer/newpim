<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Pimcore\Model\Document;
use Symfony\Component\HttpFoundation\Request;

class CatalogController extends AbstractController
{
    #[Route('/', name: 'catalog')]
    public function index(): Response
    {
        return $this->render('catalog/catalog.html.twig'); 
    }

}