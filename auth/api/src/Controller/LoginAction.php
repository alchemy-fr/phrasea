<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;

class LoginAction extends AbstractController
{
    /**
     * @Route(path="/login")
     */
    public function __invoke()
    {
        return $this->render('login.html.twig');
    }
}
