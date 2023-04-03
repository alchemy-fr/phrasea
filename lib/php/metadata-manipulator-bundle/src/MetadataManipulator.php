<?php

namespace Alchemy\MetadataManipulatorBundle;

use Alchemy\MetadataManipulatorBundle\Exception\UnknownTagGroupNameException;
use Exception;
use PHPExiftool\Driver\Metadata\Metadata;
use PHPExiftool\Driver\Metadata\MetadataBag;
use PHPExiftool\Driver\TagGroupInterface;
use PHPExiftool\PHPExiftool;
use PHPExiftool\Reader;
use PHPExiftool\Writer;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class MetadataManipulator
{
    private ?LoggerInterface $logger = null;
    private static ?array $knownTagGroups = null;  // cache
    private ?array $config;
    private string $classesDirectory;
    private PHPExiftool $phpExifTool;

    public function __construct(?array $config)
    {
        $this->config = $config;
        $cdir = $this->config['classes_directory'];
        if(substr($cdir, 0, 1) !== '/') {
            $cdir = realpath(__DIR__ . '/../../../' . $cdir);
        }
        $this->classesDirectory = $cdir;

        $this->phpExifTool = new PHPExiftool($this->classesDirectory);
    }

    /**
     * @required
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->phpExifTool->setLogger($logger);
    }
/*
    public static function getKnownTagGroups(): array
    {
        if (null === self::$knownTagGroups) {
            self::$knownTagGroups = PHPExiftool::getKnownTagGroups();
        }

        return self::$knownTagGroups;
    }
*/

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
        $reader = $this->phpExifTool->getFactory()->createReader();

        $reader->files($file->getRealPath());

        return $reader->first()->getMetadatas();
    }

    /**
     * @throws Exception
     */
    public function setMetadata(\SplFileObject $file, MetadataBag $bag): void
    {
        $writer = $this->phpExifTool->getFactory()->createWriter();
        $writer->write($file->getRealPath(), $bag);
    }

    /**
     * @return string
     */
    public function getClassesDirectory(): string
    {
        return $this->classesDirectory;
    }

    /**
     * @return PHPExiftool
     */
    public function getPhpExifTool(): PHPExiftool
    {
        return $this->phpExifTool;
    }
}
