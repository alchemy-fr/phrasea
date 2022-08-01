<?php

namespace Alchemy\MetadataManipulatorBundle;

use Exception;
use PHPExiftool\Driver\Metadata\MetadataBag;
use PHPExiftool\PHPExiftool;
use PHPExiftool\Reader;
use PHPExiftool\Writer;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class MetadataManipulator
{
    private ?LoggerInterface $logger = null;
    private static ?array $knownTagGroups = null;  // cache


    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public static function getKnownTagGroups(): array
    {
        if(is_null(self::$knownTagGroups)) {
            self::$knownTagGroups = PHPExiftool::getKnownTagGroups();;
        }

        return self::$knownTagGroups;
    }

    public function getClassnameFromTagGroupName(string $tagGroupName)
    {
        return PHPExiftool::tagGroupIdToClassname($tagGroupName);
    }

    public function getReader(): Reader
    {
        return Reader::create($this->logger ?: new NullLogger());
    }

    public function getWriter(): Writer
    {
        return Writer::create($this->logger ?: new NullLogger());
    }

    public function getMetadatas(\SplFileObject $file): MetadataBag
    {
        $reader = $this->getReader();
        $reader->files($file->getRealPath());

        return $reader->first()->getMetadatas();
    }

    /**
     * @throws Exception
     */
    public function setMetadatas(\SplFileObject $file, MetadataBag $bag): void
    {
        $writer = $this->getWriter();
        $writer->write($file->getRealPath(), $bag);
    }
}