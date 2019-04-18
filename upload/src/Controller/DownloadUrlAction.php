<?php

declare(strict_types=1);

namespace App\Controller;

use ApiPlatform\Core\Metadata\Resource\Factory\ResourceMetadataFactoryInterface;
use ApiPlatform\Core\Util\RequestAttributesExtractor;
use ApiPlatform\Core\Validator\ValidatorInterface;
use App\Model\DownloadUrl;
use Symfony\Component\HttpFoundation\Request;

final class DownloadUrlAction
{
    private $validator;
    private $resourceMetadataFactory;

    public function __construct(
        ValidatorInterface $validator,
        ResourceMetadataFactoryInterface $resourceMetadataFactory
    ) {
        $this->validator = $validator;
        $this->resourceMetadataFactory = $resourceMetadataFactory;
    }

    public function __invoke(Request $request): bool
    {
        $downloadUrl = new DownloadUrl();
        // TODO

        return true;
    }

    private function validate(DownloadUrl $downloadUrl, Request $request): void
    {
        $attributes = RequestAttributesExtractor::extractAttributes($request);
        $resourceMetadata = $this->resourceMetadataFactory->create(DownloadUrl::class);
        $validationGroups = $resourceMetadata->getOperationAttribute(
            $attributes,
            'validation_groups',
            null,
            true
        );

        $this->validator->validate($downloadUrl, ['groups' => $validationGroups]);
    }
}
