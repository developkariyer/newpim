<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class AuthController extends AbstractController
{

    #[Route('/loginIwapim', name: 'app_frontend_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('homepage');
        }
        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();
        return $this->render('auth/login.html.twig', [
            'last_username' => $lastUsername,
            'error'         => $error,
        ]);
    }

    #[Route('/loginIwapim_check', name: 'app_frontend_login_check')]
    public function check(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the Symfony security system.');
    }

    #[Route('/logoutIwapim', name: 'app_frontend_logout')]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the Symfony security system.');
    }

    #[Route('/favicon.ico', name: 'favicon')]
    public function favicon(): Response
    {
        return $this->redirectToRoute('homepage');
    }

}