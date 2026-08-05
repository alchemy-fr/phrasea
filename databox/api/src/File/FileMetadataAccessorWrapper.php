<?php

declare(strict_types=1);

namespace App\File;

use App\Entity\Core\File;
use Twig\Error\SyntaxError;

/**
 * allows calling File methods from AttributeDefinition fallback (twig), even if (asset.)file is null.
 */
class FileMetadataAccessorWrapper
{
    /** @var false|array|null */
    private $meta = false;      // false = initially unknown

    public function __construct(private readonly ?File $file)
    {
    }

    /**
     * @throws SyntaxError
     */
    public function __call($method, $args)
    {
        if (!method_exists(File::class, $method)) {
            // unknow method : fatal
            throw new SyntaxError(sprintf('Unknown function "%s"', $method));
        }
        if ($this->file) {
            return call_user_func_array([$this->file, $method], $args);
        }

        // no (asset.)file ? be nice, don't crash twig
        return null;
    }

    public function getMetadata(string $id)
    {
        if ($this->file) {
            // call file.getMetadata() only once; initial (false) means unread
            if (false === $this->meta) {
                $this->meta = $this->file->getMetadata();   // array|null
            }

            [$group, $name] = array_pad(explode(':', $id, 2), 2, null);
            if (null !== $name && is_array($this->meta) && isset($this->meta[$group][$name]) && is_array($values = $this->meta[$group][$name])) {
                // Rebuild the shape expected by Twig templates: "name", "values" and the
                // imploded "value" (not persisted, computed on read).
                return [
                    'name' => $name,
                    'values' => $values,
                    'value' => implode(' ; ', $values),
                ];
            }
        }

        return null;
    }
}
