<?php

declare(strict_types=1);

namespace App\Twig\Sandbox;

use App\File\FileMetadataAccessorWrapper;
use App\File\StringableMetadataValue;
use App\Integration\Core\Rendition\AssetAttributeAccessor;
use App\Service\Asset\Attribute\DynamicAttributeBag;
use Twig\Sandbox\SecurityNotAllowedFunctionError;
use Twig\Sandbox\SecurityNotAllowedMethodError;
use Twig\Sandbox\SecurityNotAllowedTagError;
use Twig\Sandbox\SecurityPolicyInterface;

/**
 * Policy of the templates written by workspace editors (attribute initial values
 * and fallbacks, rendition options): they are rendered by the workers, so they must
 * only read the objects given to them.
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
     */
    private const array ACCESSOR_CLASSES = [
        AssetAttributeAccessor::class,
        DynamicAttributeBag::class,
        FileMetadataAccessorWrapper::class,
        StringableMetadataValue::class,
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
        foreach (self::ACCESSOR_CLASSES as $class) {
            if ($obj instanceof $class) {
                return;
            }
        }

        // Entities (asset, file…) are given as is: only their getters may be called
        if ('__tostring' === strtolower($method) || preg_match('#^(get|is|has)[A-Z0-9_]#', $method)) {
            return;
        }

        throw new SecurityNotAllowedMethodError(sprintf('Calling "%s" method on a "%s" object is not allowed.', $method, $obj::class), $obj::class, $method);
    }

    public function checkPropertyAllowed($obj, $property): void
    {
        // Reading a public property has no side effect
    }
}
