<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Asset;
use App\Entity\Publication;
use App\Security\Voter\PublicationVoter;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/publications/{id}.{hash}.vtt", name="asset_webvtt")
 */
final class GetAssetWebVTTAction extends AbstractController
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function __invoke(string $id, string $hash): Response
    {
        /** @var Asset|null $asset */
        $asset = $this->em
            ->getRepository(Asset::class)
            ->find($id);

        if (!$asset instanceof Asset) {
            throw new NotFoundHttpException();
        }

        $response = new Response($asset->getWebVTT(), 200, [
            'Content-Type' => 'text/vtt',
        ]);
        $response->setCache([
            's_maxage' => 7776000,
            'max_age' => 7776000,
            'public' => true,
        ]);

        return $response;
    }
}
