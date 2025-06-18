<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Pimcore\Model\Document;
use Symfony\Component\HttpFoundation\Request;

class HomePageController extends AbstractController
{
    #[Route('/', name: 'homepage')]
    public function index(): Response
    {
        return $this->render('homepage/homepage.html.twig'); 
    }

    #[Route('/test', name: 'test')]
    public function test(Document $document, Request $request): Response
    {
        return $this->render($document->getTemplate(), [
            'document' => $document
        ]);
    }

}