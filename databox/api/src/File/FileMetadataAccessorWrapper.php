<?php

declare(strict_types=1);

namespace App\File;

use App\Entity\Core\File;
use App\Metadata\MetadataNormalizer;
use Twig\Error\SyntaxError;

/**
 * allows calling File methods from AttributeDefinition fallback (twig), even if (asset.)file is null.
 */
class FileMetadataAccessorWrapper
{
    private ?File $file;

    /** @var false|array|null */
    private $meta = false;      // false = initially unknown

    public function __construct(?File $file)
    {
        $this->file = $file;
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

            if (is_array($this->meta) && array_key_exists($id, $this->meta) && array_key_exists('values', $this->meta[$id])) {
                // "value" is not included in persisted normalization (for smaller json)
                $this->meta[$id]['value'] = implode(' ; ', $this->meta[$id]['values']);

                return $this->meta[$id];
            }
        }

        // no file or no metadata ? be nice also
        $r = MetadataNormalizer::createBlankMeta();
        $this->meta[$id]['value'] = null;

        return $r;
    }
}
