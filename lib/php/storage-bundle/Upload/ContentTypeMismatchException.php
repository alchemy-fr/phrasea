<?php

declare(strict_types=1);

namespace Alchemy\StorageBundle\Upload;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * The type detected from the file content does not belong to the family of the declared type.
 */
final class ContentTypeMismatchException extends BadRequestHttpException
{
    public function __construct(
        public readonly ?string $declaredType,
        public readonly string $detectedType,
    ) {
        parent::__construct(self::createMessage($declaredType, $detectedType));
    }

    public static function createMessage(?string $declaredType, string $detectedType): string
    {
        return sprintf('Declared type "%s", content is "%s"', $declaredType ?? '', $detectedType);
    }
}
