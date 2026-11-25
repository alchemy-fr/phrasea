<?php

declare(strict_types=1);

namespace Alchemy\StorageBundle\Util;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mime\MimeTypes;
use Symfony\Component\Mime\MimeTypesInterface;

/**
 * The Symfony MIME types, with the usual type of some extensions put first among
 * the types of the extension (PREFERRED_FILE_TYPES): Symfony lists "audio/x-ms-wmv"
 * before "video/x-ms-wmv" for ".wmv", and "application/mp4" before "video/mp4"
 * for ".mp4", so a ".wmv" would be guessed as audio and a ".mp4" as a document.
 *
 * The preferences are written like the allowed types, "type(.ext1,.ext2),…".
 * A preferred type unknown to Symfony for the extension is added to its types.
 *
 * The bundle aliases MimeTypesInterface to this service and makes it the default
 * instance, read by the static helpers (FileUtil) once the kernel is booted.
 */
final class PreferredMimeTypes implements MimeTypesInterface
{
    public const string DEFAULT_PREFERRED_TYPES = 'video/x-ms-wmv(.wmv),video/mp4(.mp4)';

    private static ?self $default = null;

    /**
     * @var array<string, string>|null type by extension (lower case, without dot)
     */
    private ?array $parsedTypes = null;

    /**
     * The preferences are parsed on first use: an invalid value only breaks the
     * type guessing, not every service depending on this one.
     */
    public function __construct(
        #[Autowire(env: 'PREFERRED_FILE_TYPES')]
        private readonly string $preferredTypes = self::DEFAULT_PREFERRED_TYPES,
        #[Autowire(service: 'mime_types')]
        private readonly MimeTypesInterface $mimeTypes = new MimeTypes(),
    ) {
    }

    public static function setDefault(self $default): void
    {
        self::$default = $default;
    }

    public static function getDefault(): self
    {
        return self::$default ??= new self();
    }

    public function getMimeTypes(string $ext): array
    {
        $types = $this->mimeTypes->getMimeTypes($ext);

        $preferred = $this->getPreferredTypes()[strtolower($ext)] ?? null;
        if (null === $preferred) {
            return $types;
        }

        return [$preferred, ...array_values(array_diff($types, [$preferred]))];
    }

    public function getExtensions(string $mimeType): array
    {
        return $this->mimeTypes->getExtensions($mimeType);
    }

    public function isGuesserSupported(): bool
    {
        return $this->mimeTypes->isGuesserSupported();
    }

    public function guessMimeType(string $path): ?string
    {
        return $this->mimeTypes->guessMimeType($path);
    }

    /**
     * @return array<string, string>
     */
    private function getPreferredTypes(): array
    {
        if (null !== $this->parsedTypes) {
            return $this->parsedTypes;
        }

        $preferred = [];
        foreach (FileUtil::parseTypesWithExtensions($this->preferredTypes) as $type => $extensions) {
            $type = strtolower(trim($type));
            if (str_contains($type, '*')) {
                throw new \InvalidArgumentException(sprintf('Invalid preferred file type "%s": a preferred type cannot have a wildcard', $type));
            }
            if (empty($extensions)) {
                throw new \InvalidArgumentException(sprintf('Invalid preferred file type "%s": list the extensions it is the usual type of (e.g. "video/x-ms-wmv(.wmv)")', $type));
            }

            foreach ($extensions as $extension) {
                $extension = strtolower(ltrim(trim($extension), '.'));
                if ('' !== $extension) {
                    $preferred[$extension] = $type;
                }
            }
        }

        return $this->parsedTypes = $preferred;
    }
}
