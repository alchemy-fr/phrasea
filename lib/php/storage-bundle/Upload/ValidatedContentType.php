<?php

declare(strict_types=1);

namespace Alchemy\StorageBundle\Upload;

/**
 * The types of a file whose content FileValidator accepted.
 */
final readonly class ValidatedContentType
{
    public function __construct(
        /**
         * The type libmagic detects from the content, often only the container of the format
         * ("video/x-ms-asf" for a WMA): what a later read of the same content must find again.
         */
        public string $detectedType,
        /**
         * The type that describes the file (ContentTypeDetector::resolveType()): the one to store.
         */
        public string $type,
    ) {
    }
}
