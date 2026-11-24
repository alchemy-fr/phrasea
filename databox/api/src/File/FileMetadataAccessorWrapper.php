<?php

declare(strict_types=1);

namespace App\File;

use App\Entity\Core\File;
use App\Twig\Sandbox\TemplateSecurityPolicy;
use Twig\Error\SyntaxError;

/**
 * Allows calling File methods from AttributeDefinition fallback (twig), even if (asset.)file is null.
 * Only the File methods allowed by TemplateSecurityPolicy are reachable.
 */
final readonly class FileMetadataAccessorWrapper
{
    public function __construct(private ?File $file)
    {
    }

    /**
     * @throws SyntaxError
     */
    public function __call($method, $args)
    {
        if ('metadata' === $method) {
            return isset($args[0]) ? $this->getMetadata((string) $args[0]) : null;
        }

        $resolved = self::resolveFileMethod($method);
        if (null === $resolved || !TemplateSecurityPolicy::isEntityMethodAllowed(File::class, $resolved)) {
            throw new SyntaxError(sprintf('Unknown method "%s" on file', $method));
        }

        if ($this->file) {
            return call_user_func_array([$this->file, $resolved], $args);
        }

        return null;
    }

    /**
     * Whether a template may call this method on the wrapper: the File methods
     * it forwards to are restricted by the template sandbox policy.
     */
    public static function isMethodAllowed(string $method): bool
    {
        if (in_array(strtolower($method), ['metadata', 'getmetadata'], true)) {
            return true;
        }

        $resolved = self::resolveFileMethod($method);

        return null !== $resolved && TemplateSecurityPolicy::isEntityMethodAllowed(File::class, $resolved);
    }

    /**
     * The File method a template name resolves to, with the Twig precedence
     * (`size` → getSize(), then isSize()).
     */
    private static function resolveFileMethod(string $method): ?string
    {
        $methods = [
            'get'.ucfirst($method),
            'is'.ucfirst($method),
        ];
        if (preg_match('#^(get|is|has)[A-Z0-9_]#', $method)) {
            array_unshift($methods, $method);
        }

        foreach ($methods as $m) {
            if (method_exists(File::class, $m)) {
                return $m;
            }
        }

        return null;
    }

    public function getMetadata(string $id): ?StringableMetadataValue
    {
        if ($this->file) {
            $values = $this->file->getMetadataNameValues($id);
            if (null !== $values) {
                return new StringableMetadataValue($values);
            }
        }

        return null;
    }
}
