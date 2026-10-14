<?php

declare(strict_types=1);

namespace App\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Api\Upload\UploadRequestResolver;
use App\Entity\Asset;
use App\Storage\AssetManager;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final readonly class CreateAssetProcessor implements ProcessorInterface
{
    public function __construct(
        private AssetManager $assetManager,
        private UploadRequestResolver $uploadRequestResolver,
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private ProcessorInterface $persistProcessor,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Asset
    {
        /** @var Request $request */
        $request = $context['request'];

        $publication = $this->assetManager->getPublicationWithEditGrant($this->getPublicationId($request, $uriVariables));
        $upload = $this->uploadRequestResolver->resolve($request);

        $asset = $this->assetManager->createAsset(
            $publication,
            $upload->path,
            $upload->mimeType,
            $upload->originalName,
            $upload->size,
            $request->request->all(),
        );
        if (null !== $upload->uploadUrl) {
            $asset->setUploadURL($upload->uploadUrl);
        }

        return $this->persistProcessor->process($asset, $operation, $uriVariables, $context);
    }

    private function getPublicationId(Request $request, array $uriVariables): string
    {
        $id = $request->request->get('publication_id') ?: ($uriVariables['id'] ?? null);
        if (!$id) {
            throw new BadRequestHttpException('Missing "publication_id"');
        }

        return (string) $id;
    }
}
