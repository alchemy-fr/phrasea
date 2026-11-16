<?php

declare(strict_types=1);

namespace App\Twig\Sandbox;

use App\Entity\Core\Asset;
use App\Entity\Core\File;
use App\File\FileMetadataAccessorWrapper;
use App\File\StringableMetadataValue;
use App\Integration\Core\Rendition\AssetAttributeAccessor;
use App\Service\Asset\Attribute\DynamicAttributeBag;
use Twig\Markup;
use Twig\Sandbox\SecurityNotAllowedFunctionError;
use Twig\Sandbox\SecurityNotAllowedMethodError;
use Twig\Sandbox\SecurityNotAllowedPropertyError;
use Twig\Sandbox\SecurityNotAllowedTagError;
use Twig\Sandbox\SecurityPolicyInterface;
use Twig\Template;

/**
 * Policy of the templates written by workspace editors (attribute initial values
 * and fallbacks, rendition options): they are rendered by the workers, and their
 * output may leave the platform (stamp text, watermark URL…), so they must only
 * read what is listed here from the objects given to them.
 */
final readonly class TemplateSecurityPolicy implements SecurityPolicyInterface
{
    private const array ALLOWED_TAGS = [
        'apply',
        'autoescape',
        'for',
        'if',
        'set',
        'verbatim',
        'with',
    ];

    /**
     * Functions reaching templates, PHP constants or the Twig internals.
     */
    private const array FORBIDDEN_FUNCTIONS = [
        'attribute',
        'block',
        'constant',
        'dump',
        'enum',
        'enum_cases',
        'include',
        'parent',
        'source',
        'template_from_string',
    ];

    /**
     * Accessors built for the templates: every method only reads.
     * The file wrapper is not listed: it forwards to the File methods allowed below.
     */
    private const array ACCESSOR_CLASSES = [
        AssetAttributeAccessor::class,
        DynamicAttributeBag::class,
        StringableMetadataValue::class,
    ];

    /**
     * Methods of the entities reachable by the templates, by class (`file.size` calls getSize()).
     * Anything else (owner, workspace, integrations, storage paths, related entities…)
     * would leak through a rendered text or URL.
     *
     * @var array<class-string, list<string>>
     */
    private const array ALLOWED_METHODS = [
        Asset::class => [
            '__toString',
            'getId',
            'getOwnerId',
            'getWorkspaceId',
            'getLocale',
            'getCreatedAt',
            'getUpdatedAt',
            'getEditedAt',
            'getExternalId',
            'getSource',
            'isStory',
            'hasSourceFile',
            'getSourceFileSize',
            'getSourceFileName',
            'getSourceFileType',
            'getSourceFileExtension',
            'getSourceFileFamily',
            'getSourceChecksum',
            'getSourceDocUniqueId',
        ],
        File::class => [
            '__toString',
            'getId',
            'getCreatedAt',
            'getFileName',
            'getOriginalName',
            'getExtension',
            'getExtensionWithDot',
            'getType',
            'getSize',
            'getChecksum',
            'getDocUniqueId',
            'getMetadata',
            'getMetadataValues',
            'getMetadataNameValues',
        ],
        \DateTimeInterface::class => [
            'format',
            'getTimestamp',
            'getOffset',
        ],
    ];

    /**
     * @var array<class-string, list<string>>
     */
    private const array ALLOWED_PROPERTIES = [
        Asset::class => ['name'],
    ];

    public function checkSecurity($tags, $filters, $functions, array $tests = []): void
    {
        foreach ($tags as $tag) {
            if (!in_array($tag, self::ALLOWED_TAGS, true)) {
                throw new SecurityNotAllowedTagError(sprintf('Tag "%s" is not allowed.', $tag), $tag);
            }
        }

        foreach ($functions as $function) {
            if (in_array($function, self::FORBIDDEN_FUNCTIONS, true)) {
                throw new SecurityNotAllowedFunctionError(sprintf('Function "%s" is not allowed.', $function), $function);
            }
        }
    }

    public function checkMethodAllowed($obj, $method): void
    {
        if ($obj instanceof Template || $obj instanceof Markup) {
            return;
        }

        foreach (self::ACCESSOR_CLASSES as $class) {
            if ($obj instanceof $class) {
                return;
            }
        }

        if ($obj instanceof FileMetadataAccessorWrapper) {
            if (FileMetadataAccessorWrapper::isMethodAllowed((string) $method)) {
                return;
            }
        } elseif (self::isEntityMethodAllowed($obj, (string) $method)) {
            return;
        }

        throw new SecurityNotAllowedMethodError(sprintf('Calling "%s" method on a "%s" object is not allowed.', $method, $obj::class), $obj::class, (string) $method);
    }

    public function checkPropertyAllowed($obj, $property): void
    {
        foreach (self::ACCESSOR_CLASSES as $class) {
            if ($obj instanceof $class) {
                return;
            }
        }

        foreach (self::ALLOWED_PROPERTIES as $class => $properties) {
            if ($obj instanceof $class && in_array($property, $properties, true)) {
                return;
            }
        }

        throw new SecurityNotAllowedPropertyError(sprintf('Calling "%s" property on a "%s" object is not allowed.', $property, $obj::class), $obj::class, (string) $property);
    }

    public static function isEntityMethodAllowed(object|string $objectOrClass, string $method): bool
    {
        foreach (self::ALLOWED_METHODS as $class => $methods) {
            if (is_a($objectOrClass, $class, true)) {
                foreach ($methods as $allowed) {
                    // PHP method names are case-insensitive
                    if (0 === strcasecmp($allowed, $method)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }
}
