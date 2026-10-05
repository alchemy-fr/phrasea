<?php

declare(strict_types=1);

namespace App\File;

use App\Entity\Core\File;
use Twig\Error\SyntaxError;

/**
 * Allows calling File methods from AttributeDefinition fallback (twig), even if (asset.)file is null.
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

        $methods = [
            $method,
            'get'.ucfirst($method),
            'is'.ucfirst($method),
        ];

        foreach ($methods as $m) {
            if (method_exists(File::class, $m)) {
                $method = $m;
                break;
            }
        }

        if (!method_exists(File::class, $method)) {
            throw new SyntaxError(sprintf('Unknown method "%s" on file', $method));
        }

        if ($this->file) {
            return call_user_func_array([$this->file, $method], $args);
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
