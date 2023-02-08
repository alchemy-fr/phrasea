<?php

declare(strict_types=1);

namespace App\Controller\Core;

use App\Api\Model\Input\AssetGenerateRenditionsInput;
use App\Asset\AssetManager;
use App\Consumer\Handler\Phraseanet\PhraseanetGenerateAssetRenditionsHandler;
use App\Entity\Core\Asset;
use App\Security\Voter\AbstractVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class TriggerAssetWorkflowAction extends AbstractController
{
    private EntityManagerInterface $em;
    private AssetManager $assetManager;

    public function __construct(AssetManager $assetManager, EntityManagerInterface $em)
    {
        $this->em = $em;
        $this->assetManager = $assetManager;
    }

    public function __invoke(string $id, Request $request)
    {
        $asset = $this->em->find(Asset::class, $id);
        if (!$asset instanceof Asset) {
            throw new NotFoundHttpException('Asset not found');
        }

        $this->denyAccessUnlessGranted(AbstractVoter::EDIT, $asset);

        $this->assetManager->triggerAssetWorkflow($asset);

        return new Response();
    }
}
