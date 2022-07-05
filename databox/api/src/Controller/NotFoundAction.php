<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class NotFoundAction extends AbstractController
{
    public function __invoke(): void
    {
        throw new NotFoundHttpException('API route not defined');
    }
}
