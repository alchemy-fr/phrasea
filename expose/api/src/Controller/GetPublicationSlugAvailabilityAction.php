<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Publication;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

final class GetPublicationSlugAvailabilityAction extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    #[Route(path: '/publications/slug-availability/{slug}', name: 'slug_availability')]
    public function __invoke(string $slug, Request $request): JsonResponse
    {
        /** @var Publication|null $publication */
        $publication = $this->em
            ->getRepository(Publication::class)
            ->findOneBy([
                'slug' => $slug,
            ]);

        return new JsonResponse(!$publication instanceof Publication);
    }
}
