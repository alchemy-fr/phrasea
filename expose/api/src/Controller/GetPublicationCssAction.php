<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Publication;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/publications/{id}/style.{hash}.css", name="publication_css")
 */
final class GetPublicationCssAction extends AbstractController
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
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
            's_maxage' => 7776000,
            'max_age' => 7776000,
            'public' => true,
        ]);

        return $response;
    }
}
