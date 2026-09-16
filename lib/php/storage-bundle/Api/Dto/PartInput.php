<?php

namespace Alchemy\StorageBundle\Api\Dto;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Validator\Constraints as Assert;

final class PartInput
{
    #[Assert\NotNull]
    #[Assert\NotBlank]
    #[Assert\Positive]
    public string|int|null $PartNumber = null;

    #[Assert\NotNull]
    #[Assert\NotBlank]
    public ?string $ETag = null;

    /**
     * The controllers build the input by hand (no validator pass), so the
     * shape is checked here: S3 rejects a part with a missing ETag or an
     * invalid PartNumber with an opaque "MalformedXML" error.
     */
    public static function fromArray(array $data): self
    {
        $partNumber = $data['PartNumber'] ?? null;
        if (!is_int($partNumber) && !(is_string($partNumber) && ctype_digit($partNumber))) {
            throw new BadRequestHttpException('Each part requires an integer "PartNumber"');
        }
        $partNumber = (int) $partNumber;
        if ($partNumber < 1) {
            throw new BadRequestHttpException('"PartNumber" must be greater than or equal to 1');
        }

        $eTag = $data['ETag'] ?? null;
        if (!is_string($eTag) || '' === trim($eTag)) {
            throw new BadRequestHttpException(sprintf('Part %d requires a non-empty "ETag"', $partNumber));
        }

        $part = new self();
        $part->PartNumber = $partNumber;
        $part->ETag = trim($eTag);

        return $part;
    }
}
