<?php

declare(strict_types=1);

namespace App\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Api\Upload\UploadRequestResolver;
use App\Entity\SubDefinition;
use App\Security\Voter\AssetVoter;
use App\Storage\AssetManager;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final readonly class CreateSubDefinitionProcessor implements ProcessorInterface
{
    public function __construct(
        private AssetManager $assetManager,
        private UploadRequestResolver $uploadRequestResolver,
        private Security $security,
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private ProcessorInterface $persistProcessor,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): SubDefinition
    {
        /** @var Request $request */
        $request = $context['request'];

        $assetId = $request->request->get('asset_id') ?: ($uriVariables['id'] ?? null);
        if (!$assetId) {
            throw new BadRequestHttpException('"asset_id" is required');
        }

        $name = $request->request->get('name');
        if (empty($name)) {
            throw new BadRequestHttpException('"name" is required and must not be empty');
        }

        $asset = $this->assetManager->findAsset((string) $assetId);
        if (!$this->security->isGranted(AssetVoter::EDIT, $asset)) {
            throw new AccessDeniedHttpException('Cannot add sub-definition to asset (no edit permission)');
        }

        $upload = $this->uploadRequestResolver->resolve($request);

        $subDefinition = $this->assetManager->createSubDefinition(
            $asset,
            $name,
            $upload->path,
            $upload->mimeType,
            $upload->size,
            $request->request->all(),
        );
        if (null !== $upload->uploadUrl) {
            $subDefinition->setUploadURL($upload->uploadUrl);
        }

        return $this->persistProcessor->process($subDefinition, $operation, $uriVariables, $context);
    }
}
