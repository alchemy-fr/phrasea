<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route(name="app_")
 */
class DefaultController extends AbstractController
{
    /**
     * @Route(path="/", name="index")
     */
    public function indexAction()
    {
        throw new NotFoundHttpException();
    }
}
