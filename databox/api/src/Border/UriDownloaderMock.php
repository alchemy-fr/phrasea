<?php

declare(strict_types=1);

namespace App\Border;

use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\When;

#[When('test')]
#[AsAlias(id: UriDownloader::class)]
readonly class UriDownloaderMock extends UriDownloader
{
    /**
     * @return string The temporary file path
     */
    public function download(string $uri, array &$headers = []): string
    {
        $tmpFile = sys_get_temp_dir().'/'.uniqid('incoming-file');
        $fileHandler = fopen($tmpFile, 'w');
        fwrite($fileHandler, 'foo');
        fclose($fileHandler);

        return $tmpFile;
    }
}
