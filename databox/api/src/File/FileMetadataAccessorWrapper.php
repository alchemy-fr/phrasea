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

    public function metadata(string $id)
    {
        if ($this->file) {
            // call getMetadata() only once per file
            if (false === $this->meta) {
                $this->meta = $this->file->getMetadata();
            }

            if (is_array($this->meta) && array_key_exists($id, $this->meta)) {
                // "value" is not included in persisted normalization (for smaller json)
                $this->meta[$id]['value'] = join(' ; ', $this->meta[$id]['values']);
                $this->meta[$id]['exists'] = true;  // usefull attribute for twig - not incuded into normalization

                return $this->meta[$id];
            }
        }

        // no file or no metadata ? be nice also
        $r = MetadataNormalizer::getBlankMeta();
        $this->meta[$id]['value'] = null;
        $r['exists'] = false;

        return $r;
    }
}
