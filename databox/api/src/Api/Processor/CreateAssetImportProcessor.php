<?php

declare(strict_types=1);

namespace App\Api\Processor;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Api\Model\Input\AssetImportInput;
use App\Entity\Core\AssetImport;
use App\Entity\Core\WorkspaceItemPrivacyInterface;
use App\Security\Voter\AssetContainerVoterInterface;
use App\Service\Import\AssetImportManager;
use App\Service\Storage\MultipartUploadResolver;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Stores the instructions file and creates the import, read asynchronously.
 *
 * The rights are checked here: an expression of the operation would see the input DTO.
 */
final class CreateAssetImportProcessor implements ProcessorInterface
{
    use SecurityAwareTrait;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AssetImportManager $importManager,
        private readonly MultipartUploadResolver $multipartUploadResolver,
    ) {
    }

    /**
     * @param AssetImportInput $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): AssetImport
    {
        $user = $this->getStrictUser();
        $workspace = $data->workspace;
        $collection = $data->collection;

        if (null !== $collection) {
            if ($collection->getWorkspaceId() !== $workspace->getId()) {
                throw new BadRequestHttpException('The collection does not belong to the workspace');
            }
            $this->denyAccessUnlessGranted(AssetContainerVoterInterface::ASSET_CREATE, $collection, 'Cannot create assets in this collection');
        } else {
            $this->denyAccessUnlessGranted(AssetContainerVoterInterface::ASSET_CREATE, $workspace, 'Cannot create assets in this workspace');
        }

        if (null !== $data->privacy && !array_key_exists($data->privacy, WorkspaceItemPrivacyInterface::KEYS)) {
            throw new BadRequestHttpException(sprintf('Invalid privacy "%d"', $data->privacy));
        }

        if ((null === $data->multipart) === (null === $data->data)) {
            throw new BadRequestHttpException('Provide the instructions file in either "multipart" or "data"');
        }

        $originalName = $data->originalName;
        if (null !== $data->multipart) {
            $upload = $this->multipartUploadResolver->resolve($data->multipart);
            $sourcePath = $upload->getPath();
            $originalName ??= $upload->getFilename();
        } else {
            if ('' === trim($data->data)) {
                throw new BadRequestHttpException('"data" is empty');
            }
            $sourcePath = $this->importManager->storeContent($workspace, $data->format, $data->data);
        }

        $import = $this->importManager->createImport(
            $workspace,
            $data->format,
            $sourcePath,
            $user->getId(),
            collection: $collection,
            privacy: $data->privacy,
            originalName: $originalName,
        );
        $this->em->flush();

        return $import;
    }
}
