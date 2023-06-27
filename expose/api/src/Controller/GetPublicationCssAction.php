<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Publication;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/publications/{id}/style.{hash}.css', name: 'publication_css')]
final class GetPublicationCssAction extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function __invoke(string $id, string $hash): Response
    {
        /** @var Publication|null $publication */
        $publication = $this->em
            ->getRepository(Publication::class)
            ->find($id);

        if (!$publication instanceof Publication) {
            throw new NotFoundHttpException();
        }

        $response = new Response($publication->getCss(), 200, [
            'Content-Type' => 'text/css',
        ]);
        $response->setCache([
            's_maxage' => 7_776_000,
            'max_age' => 7_776_000,
            'public' => true,
        ]);

        return $response;
    }
}
