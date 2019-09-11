<?php

declare(strict_types=1);

namespace App\User\Import\Loader;

use App\User\Import\ColReader\ColReaderInterface;
use App\User\Import\ColReader\EmailColReader;
use App\User\Import\ColReader\EnabledReader;
use App\User\Import\ColReader\IsAdminColReader;

class CsvImportLoader implements UserImportLoaderInterface
{
    /**
     * @var array
     */
    private $colReaders = [];

    public function __construct()
    {
        $this->colReaders = [
            new EmailColReader(),
            new IsAdminColReader(),
            new EnabledReader(),
        ];
    }

    public function import($resource, callable $createUser): iterable
    {
        $header = fgetcsv($resource, 1000, ',');
        if (empty($header)) {
            return [];
        }
        $columnReaders = $this->getColumnReaders($header);

        while (false !== ($data = fgetcsv($resource, 1000, ','))) {
            $user = $createUser();
            foreach ($columnReaders as $i => $transformer) {
                if (null !== $transformer) {
                    $transformer($data[$i], $user);
                }
            }

            yield $user;
        }
        fclose($resource);
    }

    /**
     * @return ColReaderInterface[]
     */
    private function getColumnReaders(array $headers): array
    {
        $cols = [];
        foreach ($headers as $col) {
            $cols[] = $this->getSupportedColReader($col);
        }

        return $cols;
    }

    private function getSupportedColReader(string $colName): ?ColReaderInterface
    {
        foreach ($this->colReaders as $colReader) {
            if ($colReader->supports($colName)) {
                return $colReader;
            }
        }

        return null;
    }
}
