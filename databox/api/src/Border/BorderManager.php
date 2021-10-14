<?php

declare(strict_types=1);

namespace App\Border;

use App\Border\Exception\FileInputValidationException;
use App\Border\Model\FileContent;
use App\Border\Model\InputFile;

class BorderManager
{
    public function acceptFile(InputFile $file): bool
    {
        try {
            $this->validateFile($file);

            $path = $this->importFile($file);

            $content = new FileContent($file, $path);
            $this->validateContent($content);

            return true;
        } catch (FileInputValidationException $e) {
            return false;
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
