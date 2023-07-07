<?php

declare(strict_types=1);

namespace Alchemy\StorageBundle\Upload;

use Alchemy\StorageBundle\Util\FileUtil;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class FileValidator
{
    /**
     * @var array{string, string[]}
     */
    private array $allowedTypes;

    public function __construct(
        array|string $allowedTypes,
    ) {
        $this->allowedTypes = $this->normalizeTypes($allowedTypes);
    }

    private function normalizeTypes(array|string $value): array
    {
        if (is_string($value)) {
            $types = [];
            preg_match_all('/([\w*]+\/[\w*]+)(\([\w,]*\))?/', $value, $matches);

            foreach ($matches[0] as $i => $match) {
                $extensions = [];

                if (!empty($matches[2][$i])) {
                    $extensions = array_map(function (string $ext): string {
                        return preg_replace('#^\.#', '', trim($ext));
                    }, explode(',', substr($matches[2][$i], 1, -1)));
                }

                $types[$matches[1][$i]] = $extensions;
            }

            return $types;
        }

        return $value;
    }

    private function getAllowedExtensions(): array
    {
        return array_merge(...array_values($this->allowedTypes));
    }

    public function validateFile(string $path, ?string $type): void
    {
        if (empty($this->allowedTypes)) {
            return;
        }

        $extension = FileUtil::getExtensionFromPath($path);
        if (null === $type) {
            $type = FileUtil::getTypeFromExtension($extension);
        }

        if (!$this->hasValidType($type)) {
            throw $this->createException($type, $this->allowedTypes, 'type');
        }

        if (!$this->hasValidExtension($extension)) {
           throw $this->createException($extension, $this->getAllowedExtensions(), 'extension');
        }
    }

    private function createException(string $value, array $allowed, string $type): BadRequestHttpException
    {
        return new BadRequestHttpException(sprintf('File %1$s "%2$s" is not allowed. Allowed %1$ss are %3$s',
            $type,
            $value,
            implode(', ', $allowed),
        ));
    }

    private function hasValidExtension(string $extension): bool
    {
        $allowedExtensions = $this->getAllowedExtensions();

        if (in_array($extension, $allowedExtensions, true)) {
            return true;
        }

        foreach ($allowedExtensions as $allowedExtension) {
            if ($this->matches($extension, $allowedExtension)) {
                return true;
            }
        }

        return false;
    }

    private function hasValidType(string $type): bool
    {
        $allowedTypes = array_keys($this->allowedTypes);

        if (in_array($type, $allowedTypes, true)) {
            return true;
        }

        foreach ($allowedTypes as $allowedType) {
            if ($this->matches($type, $allowedType)) {
                return true;
            }
        }

        return false;
    }

    private function matches(string $value, string $mask): bool
    {
        return 1 === preg_match('#^'.str_replace('*', '.+', $mask).'$#', $value);
    }
}
