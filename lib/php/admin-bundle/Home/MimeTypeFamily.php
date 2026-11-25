<?php

declare(strict_types=1);

namespace Alchemy\AdminBundle\Home;

/**
 * The file families of the admin home charts, guessed from the MIME type only (the same table
 * as the rendition factory, without reading the file).
 */
final class MimeTypeFamily
{
    public const string IMAGE = 'Image';
    public const string VIDEO = 'Video';
    public const string AUDIO = 'Audio';
    public const string DOCUMENT = 'Document';
    public const string OTHER = 'Other';

    private const array FAMILIES = [
        'application/pdf' => self::DOCUMENT,
        'text/rtf' => self::DOCUMENT,
        'text/plain' => self::DOCUMENT,
        'application/rtf' => self::DOCUMENT,
        'application/msword' => self::DOCUMENT,
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => self::DOCUMENT,
        'application/vnd.ms-excel' => self::DOCUMENT,
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => self::DOCUMENT,
        'application/vnd.oasis.opendocument.text' => self::DOCUMENT,
        'application/vnd.oasis.opendocument.spreadsheet' => self::DOCUMENT,

        'application/mxf' => self::VIDEO,
        'application/ogg' => self::VIDEO,

        'application/postscript' => self::IMAGE,
        'application/x-photoshop' => self::IMAGE,
        'application/photoshop' => self::IMAGE,
        'application/psd' => self::IMAGE,
        'application/vnd.3gpp.pic-bw-small' => self::IMAGE,
        'application/illustrator' => self::IMAGE,
    ];

    public static function getFamily(string $mimeType): string
    {
        $mimeType = strtolower(trim(explode(';', $mimeType, 2)[0]));

        return match (true) {
            str_starts_with($mimeType, 'image/') => self::IMAGE,
            str_starts_with($mimeType, 'video/') => self::VIDEO,
            str_starts_with($mimeType, 'audio/') => self::AUDIO,
            default => self::FAMILIES[$mimeType] ?? self::OTHER,
        };
    }

    /**
     * @param array<string, int> $counts indexed by MIME type
     *
     * @return array<string, int> indexed by family, the largest one first
     */
    public static function aggregate(array $counts): array
    {
        $families = [];
        foreach ($counts as $mimeType => $count) {
            $family = self::getFamily((string) $mimeType);
            $families[$family] = ($families[$family] ?? 0) + $count;
        }
        arsort($families);

        return $families;
    }
}
