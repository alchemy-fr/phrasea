<?php

namespace Alchemy\MetadataManipulatorBundle;

use PHPExiftool\Driver\Metadata\Metadata;
use PHPExiftool\Driver\Metadata\MetadataBag;
use PHPExiftool\Driver\TagGroupInterface;
use PHPExiftool\Exception\ExceptionInterface as PHPExiftoolExceptionInterface;
use PHPExiftool\Exiftool;
use PHPExiftool\PHPExiftool;
use PHPExiftool\RDFParser;
use PHPExiftool\Reader;
use PHPExiftool\Writer;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class MetadataManipulator
{
    /** Installed in the worker image, runs exiftool in the conversion container (CONVERTER_URL) */
    public const string CONVERTER_EXIFTOOL = '/usr/local/libexec/convert/exiftool';

    private ?array $knownTagGroups = null;  // cache
    private readonly PHPExiftool $phpExifTool;
    private readonly LoggerInterface $logger;
    /** null: the exiftool of phpexiftool (vendor/exiftool/exiftool) */
    private readonly ?string $exiftoolBinary;

    /**
     * @param string|null $converterUrl when not empty, exiftool reads and writes the files through
     *                                  $converterExiftool, in the conversion container (the tag
     *                                  classes are still generated with the local exiftool, of the
     *                                  same version)
     */
    public function __construct(
        string $classesDirectory,
        ?LoggerInterface $logger = null,
        bool $debug = false,
        ?string $converterUrl = null,
        string $converterExiftool = self::CONVERTER_EXIFTOOL,
    ) {
        $this->phpExifTool = new PHPExiftool($classesDirectory);
        $this->logger = $logger ?? new NullLogger();
        $this->exiftoolBinary = null !== $converterUrl && '' !== $converterUrl ? $converterExiftool : null;

        if ($debug) {
            $this->phpExifTool->setLogger($this->logger);
        }
    }

    /**
     * @return string[] The known tag group ids (e.g. "IPTC:Keywords"), naturally sorted
     */
    public function getKnownTagGroups(): array
    {
        return $this->knownTagGroups ??= array_keys($this->phpExifTool->getFactory()->getHelper()::getIndex());
    }

    public function createTagGroup(string $tagGroupName): TagGroupInterface
    {
        return $this->phpExifTool->getFactory()->createTagGroup($tagGroupName);
    }

    public function createMetadata(string $tagGroupName): Metadata
    {
        return new Metadata($this->createTagGroup($tagGroupName));
    }

    public function getAllMetadata(\SplFileObject $file): MetadataBag
    {
        $reader = Reader::create(
            $this->createExiftool(),
            new RDFParser($this->phpExifTool->getClassesRootDirectory(), $this->phpExifTool->getLogger()),
        );

        $reader->files($file->getRealPath());

        try {
            return $reader->first()->getMetadatas();
        } catch (PHPExiftoolExceptionInterface $e) {
            // exiftool exits with an error (and no output) on empty, truncated or
            // unknown files: treat it as "no metadata" rather than failing the caller.
            $this->logger->warning('Unable to read metadata with exiftool, returning an empty bag', [
                'file' => $file->getRealPath(),
                'error' => $e->getMessage(),
            ]);

            return new MetadataBag();
        }
    }

    public function createWriter(): Writer
    {
        return Writer::create($this->createExiftool());
    }

    /**
     * @throws \Exception
     */
    public function setMetadata(\SplFileObject $file, MetadataBag $bag): void
    {
        $this->createWriter()->write($file->getRealPath(), $bag);
    }

    public function getClassesDirectory(): string
    {
        return $this->phpExifTool->getClassesRootDirectory();
    }

    public function getPhpExifTool(): PHPExiftool
    {
        return $this->phpExifTool;
    }

    public function getExiftoolBinary(): ?string
    {
        return $this->exiftoolBinary;
    }

    private function createExiftool(): Exiftool
    {
        return new Exiftool($this->phpExifTool->getLogger(), $this->exiftoolBinary);
    }
}
