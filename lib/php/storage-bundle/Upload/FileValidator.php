<?php

declare(strict_types=1);

namespace Alchemy\StorageBundle\Upload;

use Alchemy\StorageBundle\Storage\InlineContentTypePolicy;
use Alchemy\StorageBundle\Util\FileUtil;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Mime\MimeTypes;

/**
 * Checks the (MIME type, extension) couple of an uploaded file.
 *
 * Allowed types are written "mask(.ext1,.ext2),mask2(…)" (ALLOWED_FILE_TYPES):
 * a file is accepted when one mask matches its type AND lists its extension.
 * A mask without extensions accepts the extensions registered for the file type
 * (Symfony MIME database). "*" only stands for the subtype ("image/*"): a mask
 * with a wildcard main type, which would accept any type, is refused.
 * An empty configuration accepts every file.
 */
final class FileValidator
{
    /**
     * @var array<string, string[]>|null extensions (lower case, without dot) by type mask
     */
    private ?array $parsedTypes = null;

    /**
     * The configuration is parsed on first use: an invalid one only breaks uploads,
     * not every service depending on this one.
     *
     * @param array<string, string[]>|string $allowedTypes
     */
    public function __construct(
        private readonly array|string $allowedTypes,
    ) {
    }

    /**
     * @return array<string, string[]>
     */
    private function getAllowedTypes(): array
    {
        return $this->parsedTypes ??= $this->normalizeTypes($this->allowedTypes);
    }

    private function normalizeTypes(array|string $value): array
    {
        if (is_string($value)) {
            $types = [];
            preg_match_all('#([\w*]+/[\w*+.-]+)\s*(\([^)]*\))?#', $value, $matches);

            foreach ($matches[1] as $i => $mask) {
                $extensions = [];
                if (!empty($matches[2][$i])) {
                    $extensions = explode(',', substr($matches[2][$i], 1, -1));
                }

                $types[$mask] = array_merge($types[$mask] ?? [], $extensions);
            }

            $value = $types;
        }

        $normalized = [];
        foreach ($value as $mask => $extensions) {
            $mask = strtolower(trim((string) $mask));
            if (str_starts_with($mask, '*/')) {
                throw new \InvalidArgumentException(sprintf('Invalid allowed file type "%s": the main type cannot be a wildcard, list the actual MIME types with their extensions (e.g. "application/x-indesign(.indd)")', $mask));
            }

            $normalized[$mask] = array_values(array_unique(array_filter(array_map(
                static fn (string $ext): string => strtolower(ltrim(trim($ext), '.')),
                $extensions,
            ))));
        }

        return $normalized;
    }

    public function validateFile(string $path, ?string $type): void
    {
        if (empty($this->getAllowedTypes())) {
            return;
        }

        $extension = FileUtil::getExtensionFromPath($path);
        if (null === $type) {
            $type = FileUtil::getTypeFromExtension($extension);
        }
        $type = InlineContentTypePolicy::normalize($type);

        if (null === $type || null === $extension || !$this->isAllowed($type, $extension)) {
            throw new BadRequestHttpException(sprintf('File type "%s" with extension "%s" is not allowed. Allowed types are %s', $type ?? '', $extension ?? '', $this->describeAllowedTypes()));
        }
    }

    private function isAllowed(string $type, string $extension): bool
    {
        foreach ($this->getAllowedTypes() as $mask => $extensions) {
            if (!$this->matches($type, $mask)) {
                continue;
            }

            if (empty($extensions)) {
                $extensions = (new MimeTypes())->getExtensions($type);
            }

            if (in_array($extension, $extensions, true)) {
                return true;
            }
        }

        return false;
    }

    private function describeAllowedTypes(): string
    {
        $descriptions = [];
        foreach ($this->getAllowedTypes() as $mask => $extensions) {
            $descriptions[] = empty($extensions)
                ? $mask
                : sprintf('%s (%s)', $mask, implode(', ', array_map(static fn (string $ext): string => '.'.$ext, $extensions)));
        }

        return implode(', ', $descriptions);
    }

    private function matches(string $type, string $mask): bool
    {
        if ($type === $mask) {
            return true;
        }

        return 1 === preg_match('#^'.str_replace('\*', '[^/]+', preg_quote($mask, '#')).'$#', $type);
    }
}
