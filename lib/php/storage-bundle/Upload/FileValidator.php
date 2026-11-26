<?php

declare(strict_types=1);

namespace Alchemy\StorageBundle\Upload;

use Alchemy\StorageBundle\Storage\InlineContentTypePolicy;
use Alchemy\StorageBundle\Util\FileUtil;
use Psr\Log\LoggerInterface;
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
 *
 * An extension listed under a mask that matches none of its types (".indd" under
 * "image/*") is refused like any other couple: the refusal names this misplaced
 * extension, and a warning is logged when the configuration is parsed for the
 * extensions whose types the MIME database knows.
 */
final class FileValidator
{
    /**
     * @var array<string, string[]>|null extensions (lower case, without dot) by type mask
     */
    private ?array $parsedTypes = null;

    /**
     * @var array<string, array{masks: string[], types: string[]}>|null
     */
    private ?array $misplacedExtensions = null;

    /**
     * The configuration is parsed on first use: an invalid one only breaks uploads,
     * not every service depending on this one.
     *
     * @param array<string, string[]>|string $allowedTypes
     */
    public function __construct(
        private readonly array|string $allowedTypes,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * @return array<string, string[]>
     */
    private function getAllowedTypes(): array
    {
        if (null === $this->parsedTypes) {
            $this->parsedTypes = $this->normalizeTypes($this->allowedTypes);
            $this->warnMisplacedExtensions();
        }

        return $this->parsedTypes;
    }

    /**
     * The extensions listed under a mask that matches none of their registered types,
     * nor the application/* types libmagic gives to some files of their family
     * (".epsf" under "application/*" is "application/postscript"): that mask never
     * accepts their files. Extensions unknown to the MIME database and the
     * application/octet-stream mask (clients that do not know the type) are left out.
     *
     * @return array<string, array{masks: string[], types: string[]}> by extension
     */
    private function getMisplacedExtensions(): array
    {
        if (null !== $this->misplacedExtensions) {
            return $this->misplacedExtensions;
        }

        $misplaced = [];
        foreach ($this->getAllowedTypes() as $mask => $extensions) {
            if ('application/octet-stream' === $mask) {
                continue;
            }

            foreach ($extensions as $extension) {
                $types = FileUtil::getTypesFromExtension($extension);
                if (empty($types)) {
                    continue;
                }

                $candidates = $types;
                foreach ($types as $type) {
                    array_push($candidates, ...ContentTypeDetector::getApplicationTypesOfFamily($type));
                }
                foreach ($candidates as $candidate) {
                    if ($this->matches($candidate, $mask)) {
                        continue 2;
                    }
                }

                $misplaced[$extension]['masks'][] = $mask;
                $misplaced[$extension]['types'] = $types;
            }
        }

        return $this->misplacedExtensions = $misplaced;
    }

    private function warnMisplacedExtensions(): void
    {
        if (null === $this->logger) {
            return;
        }

        $misplaced = $this->getMisplacedExtensions();
        if (empty($misplaced)) {
            return;
        }

        $descriptions = [];
        foreach ($misplaced as $extension => ['masks' => $masks, 'types' => $types]) {
            $descriptions[] = sprintf('".%s" is listed under %s, which matches none of its types (%s)', $extension, implode(', ', $masks), implode(', ', $types));
        }

        $this->logger->warning(sprintf('Allowed file types: %s; these entries never accept such files. List these extensions under their own types.', implode('; ', $descriptions)), [
            'misplaced' => $misplaced,
        ]);
    }

    /**
     * Explains the refusal of an extension listed only under masks that match none
     * of its types, or, for an extension unknown to the MIME database (".indd"),
     * none of which matches the type of the refused file.
     */
    private function describeMisplacedExtension(string $extension, ?string $type): ?string
    {
        $masks = [];
        foreach ($this->getAllowedTypes() as $mask => $extensions) {
            if (in_array($extension, $extensions, true)) {
                $masks[] = $mask;
            }
        }
        if (empty($masks)) {
            return null;
        }

        if (empty(FileUtil::getTypesFromExtension($extension))) {
            if (null === $type) {
                return null;
            }

            // The file was refused: none of these masks matches its type
            return sprintf('The configuration lists ".%s" under %s only, which does not match the type of this file (%s).', $extension, implode(', ', $masks), $type);
        }

        $misplaced = $this->getMisplacedExtensions()[$extension] ?? null;
        if (null === $misplaced || $misplaced['masks'] !== $masks) {
            return null;
        }

        $types = $misplaced['types'];

        return sprintf(
            'The configuration lists ".%s" under %s only, which none of its types (%s) matches: list it under %s or %s/*.',
            $extension,
            implode(', ', $misplaced['masks']),
            implode(', ', $types),
            $types[0],
            explode('/', $types[0], 2)[0],
        );
    }

    private function normalizeTypes(array|string $value): array
    {
        if (is_string($value)) {
            $value = FileUtil::parseTypesWithExtensions($value);
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
            $diagnostic = null !== $extension ? $this->describeMisplacedExtension($extension, $type) : null;

            throw new BadRequestHttpException(sprintf('File type "%s" with extension "%s" is not allowed.%s Allowed types are %s', $type ?? '', $extension ?? '', null !== $diagnostic ? ' '.$diagnostic : '', $this->describeAllowedTypes()));
        }
    }

    /**
     * Checks the content of a received file: forbidden formats, consistency with
     * the declared type, then the allowed types for the type of the content.
     *
     * @throws BadRequestHttpException
     */
    public function validateFileContent(string $path, string $filename, ?string $declaredType): ValidatedContentType
    {
        return $this->validateHead(ContentTypeDetector::readHead($path), $filename, $declaredType);
    }

    /**
     * @param string $head the first bytes of the file (ContentTypeDetector::HEAD_SIZE)
     *
     * @throws BadRequestHttpException
     */
    public function validateHead(string $head, string $filename, ?string $declaredType): ValidatedContentType
    {
        $detector = new ContentTypeDetector();
        $detectedType = $detector->checkBuffer($head, $declaredType);
        $type = $detector->resolveType($declaredType, $detectedType, $filename);
        $this->validateFile($filename, $type);

        return new ValidatedContentType($detectedType, $type);
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
