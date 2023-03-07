<?php

declare(strict_types=1);

namespace App\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route(path="/n8n")
 */
class N8nController extends AbstractController
{
    /**
     * @Route(path="/sleep", methods={"GET"})
     */
    public function __invoke(Request $request, EntityManagerInterface $em): Response
    {
        $time = (int)$request->query->get('t', 0);
        sleep($time);

        return new JsonResponse([
            'foo' => 'bar',
            'slept' => $time,
        ]);
    }
}
