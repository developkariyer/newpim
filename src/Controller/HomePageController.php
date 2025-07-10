<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class HomePageController extends AbstractController
{
    #[Route('/', name: 'homepage')]
    public function homepage(): Response
    {
        $user = $this->getUser();
        return $this->render('homepage/homepage.html.twig', [
            'user' => $user,
        ]);
    }
}