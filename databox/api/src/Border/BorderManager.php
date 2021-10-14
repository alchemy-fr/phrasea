<?php

declare(strict_types=1);

namespace App\Border;

use App\Border\Exception\FileInputValidationException;
use App\Border\Model\FileContent;
use App\Border\Model\InputFile;
use App\Entity\Core\File;
use Doctrine\ORM\EntityManagerInterface;

class BorderManager
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function acceptFile(InputFile $inputFile): ?File
    {
        try {
            $this->validateFile($inputFile);

            $path = $this->importFile($inputFile);

            $content = new FileContent($inputFile, $path);
            $this->validateContent($content);

            $file = new File();
            $file->setPath($content->getPath());
            $file->setSize($inputFile->getSize());
            $file->setType($inputFile->getType());

            $this->em->persist($file);
            $this->em->flush();

            return $file;
        } catch (FileInputValidationException $e) {
            return null;
        }
    }

    public function validateFile(InputFile $file): void
    {
    }

    public function validateContent(FileContent $fileContent): void
    {
    }

    private function importFile(InputFile $file): string
    {
        return 'TODO';
    }
}
