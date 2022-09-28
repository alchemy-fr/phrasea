<?php

namespace App\File;

use App\Entity\Core\File;
use App\Metadata\MetadataNormalizer;
use Psr\Log\LoggerInterface;
use Twig\Error\SyntaxError;

/**
 * allows calling File methods from AttributeDefinition fallback (twig), even if (asset.)file is null
 */
class FileMetadataAccessorWrapper
{
    private ?File $file;

    /** @var false|null|array */
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
        if(!method_exists(File::class, $method)) {
            // unknow method : fatal
            throw new SyntaxError(sprintf("Unknown function \"%s\"", $method));
        }
        if($this->file) {
            return call_user_func_array([$this->file, $method], $args);
        }

        // no (asset.)file ? be nice, don't crash twig
        return null;
    }

    public function metadata(string $id)
    {
        if ($this->file) {
            // call getMetadata() only once per file
            if($this->meta === false) {
                $this->meta = $this->file->getMetadata();
            }

            if( is_array($this->meta) && array_key_exists($id, $this->meta)) {
                $this->meta[$id]['exists'] = true;  // usefull attribute for twig - not incuded into normalization
                return $this->meta[$id];
            }
        }

        // no file or no metadata ? be nice also
        $r = MetadataNormalizer::getBlankMeta();
        $r['exists'] = false;
        return $r;
    }
}
