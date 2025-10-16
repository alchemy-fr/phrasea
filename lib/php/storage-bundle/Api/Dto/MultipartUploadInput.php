<?php

namespace Alchemy\StorageBundle\Api\Dto;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Validator\Constraints as Assert;

class MultipartUploadInput
{
    /**
     * List of uploaded parts.
     *
     * @var PartInput[]
     */
    #[Assert\NotNull]
    #[Assert\Count(min: 1)]
    #[Assert\Valid]
    public ?array $parts;

    #[Assert\NotBlank]
    #[Assert\NotNull]
    public ?string $uploadId = null;

    public static function fromRequest(Request $request): self
    {
        return self::fromArray($request->request->all('multipart'));
    }

    public static function fromArray(array $data): self
    {
        $input = new self();
        $uploadId = $data['uploadId'] ?? null;
        if (empty($uploadId)) {
            throw new BadRequestHttpException('uploadId is required');
        }
        $parts = $data['parts'] ?? [];
        if (!is_array($parts) || empty($parts)) {
            throw new BadRequestHttpException('At least one part is required');
        }

        $input->uploadId = $uploadId;
        $input->parts = array_map(
            fn (array $part) => PartInput::fromArray($part),
            $parts
        );

        return $input;
    }
}
