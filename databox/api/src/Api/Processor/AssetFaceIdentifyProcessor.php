<?php

declare(strict_types=1);

namespace App\Api\Processor;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use Alchemy\CoreBundle\Util\DoctrineUtil;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Api\Model\Input\AssetFaceInput;
use App\Entity\Core\AssetFace;
use App\Entity\Integration\WorkspaceIntegration;
use App\Integration\Core\FaceRecognition\FaceIdentifier;
use App\Integration\Core\FaceRecognition\FaceRecognitionIntegration;
use App\Integration\IntegrationManager;
use App\Security\Voter\AssetFaceVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Names (or unnames) a face on behalf of the user, through the face recognition
 * integration of the workspace so that attributes and similar faces follow.
 */
final class AssetFaceIdentifyProcessor implements ProcessorInterface
{
    use SecurityAwareTrait;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly IntegrationManager $integrationManager,
        private readonly FaceIdentifier $identifier,
    ) {
    }

    /**
     * @param AssetFaceInput $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): AssetFace
    {
        $face = DoctrineUtil::findStrict($this->em, AssetFace::class, (string) $uriVariables['id']);
        $this->denyAccessUnlessGranted(AssetFaceVoter::EDIT, $face);

        $workspaceIntegration = $this->em->getRepository(WorkspaceIntegration::class)->findOneBy([
            'workspace' => $face->getAsset()->getWorkspaceId(),
            'integration' => FaceRecognitionIntegration::getName(),
            'enabled' => true,
        ]);
        if (!$workspaceIntegration instanceof WorkspaceIntegration) {
            throw new BadRequestHttpException('Face recognition is not enabled in the workspace of this asset');
        }

        $this->identifier->identify($face, $data->identity, $this->integrationManager->getIntegrationConfiguration($workspaceIntegration));

        return $face;
    }
}
