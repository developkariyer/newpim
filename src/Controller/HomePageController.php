<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class HomePageController extends AbstractController
{
    #[Route('/', name: 'homepage')]
    public function homepage(): Response
    {
        $lastUsername = $authenticationUtils->getLastUsername();
        return $this->render('homepage/homepage.html.twig', [
            'user' => $lastUsername,
        ]);
    }
}