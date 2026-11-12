<?php

declare(strict_types=1);

namespace App\Border;

use Alchemy\StorageBundle\Util\FileUtil;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\When;

#[When('test')]
#[AsAlias(id: UriDownloader::class)]
readonly class UriDownloaderMock extends UriDownloader
{
    /**
     * A 1x1 PNG, also served for the other image extensions (same family).
     */
    private const string PNG = "\x89PNG\r\n\x1a\n\x00\x00\x00\rIHDR\x00\x00\x00\x01\x00\x00\x00\x01\x08\x06\x00\x00\x00\x1f\x15\xc4\x89\x00\x00\x00\rIDATx\xdac\xf8\xcf\xc0\xf0\x1f\x00\x05\x00\x01\xff\x89\x99=\x1d\x00\x00\x00\x00IEND\xaeB`\x82";
    private const string PDF = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n";
    private const string MP4 = "\x00\x00\x00\x18ftypmp42\x00\x00\x00\x00mp42isom\x00\x00\x00\x08free";

    /**
     * @return string The temporary file path
     */
    #[\Override]
    public function download(string $uri, array &$headers = [], ?string $path = null, bool $allowPrivateNetwork = false): string
    {
        $path ??= sys_get_temp_dir().'/'.uniqid('incoming-file');
        file_put_contents($path, self::getContent($uri));

        return $path;
    }

    #[\Override]
    public function downloadHead(string $uri, int $length, bool $allowPrivateNetwork = false): string
    {
        return substr(self::getContent($uri), 0, $length);
    }

    /**
     * A content of the type of the URL extension, so that the content check accepts it.
     */
    public static function getContent(string $uri): string
    {
        $type = FileUtil::getTypeFromExtension(FileUtil::getExtensionFromPath($uri)) ?? '';

        return match (true) {
            str_starts_with($type, 'image/') => self::PNG,
            'application/pdf' === $type => self::PDF,
            str_starts_with($type, 'video/'), str_starts_with($type, 'audio/') => self::MP4,
            default => 'foo',
        };
    }
}
