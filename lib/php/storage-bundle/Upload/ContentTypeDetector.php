<?php

declare(strict_types=1);

namespace Alchemy\StorageBundle\Upload;

use Alchemy\StorageBundle\Storage\InlineContentTypePolicy;

/**
 * Detects the type of a file from its content (libmagic, on its first bytes only)
 * and tells whether it matches the type declared by the client.
 *
 * Types are compared by family (image, audio/video, document, other) rather than
 * exactly: libmagic often names a format differently from browsers, or only knows
 * its container. See ContentTypeDetectorTest for what libmagic returns for each
 * allowed format.
 */
final readonly class ContentTypeDetector
{
    /**
     * Bytes read to detect the type: enough for libmagic to recognize every
     * supported format, including Office documents (zip entries).
     */
    public const int HEAD_SIZE = 65536;

    public const string FAMILY_IMAGE = 'image';
    /**
     * A single family: libmagic cannot tell an audio-only MP4 or OGG from a video.
     */
    public const string FAMILY_AUDIO_VIDEO = 'audio_video';
    public const string FAMILY_DOCUMENT = 'document';
    public const string FAMILY_OTHER = 'other';

    private const string UNKNOWN_TYPE = 'application/octet-stream';

    private const array IMAGE_TYPES = [
        'application/postscript',
        'application/illustrator',
        'application/x-photoshop',
        'application/photoshop',
        'application/psd',
        'application/vnd.3gpp.pic-bw-small',
    ];

    private const array AUDIO_VIDEO_TYPES = [
        'application/mp4',
        'application/ogg',
        'application/mxf',
    ];

    private const array DOCUMENT_TYPES = [
        'application/pdf',
        'application/x-pdf',
        'application/acrobat',
        'application/nappdf',
        'application/rtf',
        'application/msword',
        'application/vnd.ms-word',
        'application/x-msword',
        'application/vnd.ms-excel',
        'application/msexcel',
        'application/x-msexcel',
        'application/vnd.ms-powerpoint',
        'application/vnd.ms-office',
        'application/csv',
        'text/plain',
        'text/rtf',
        'text/csv',
        'text/x-csv',
        'text/x-comma-separated-values',
    ];

    private const array DOCUMENT_TYPE_PREFIXES = [
        'application/vnd.openxmlformats-officedocument.',
        'application/vnd.oasis.opendocument.',
    ];

    /**
     * Types libmagic returns for the container of a format it does not know, by
     * declared type (regex). The family of the container differs from the
     * declared one, the content is still what was declared.
     */
    private const array CONTAINER_TYPES = [
        '#^application/vnd\.(openxmlformats-officedocument|oasis\.opendocument)\.#' => [
            'application/zip',
        ],
        '#^application/(msword|vnd\.ms-word|x-msword|vnd\.ms-excel|msexcel|x-msexcel|vnd\.ms-powerpoint|vnd\.ms-office|vnd\.visio)$#' => [
            'application/x-ole-storage',
            'application/cdfv2',
            'application/vnd.ms-office',
        ],
        // A raw HEVC/H.264 stream has no header libmagic knows
        '#^video/(h264|h265|hevc|x-h264|x-h265|x-hevc)$#' => [
            self::UNKNOWN_TYPE,
        ],
        '#^image/svg\+xml$#' => [
            'text/xml',
            'application/xml',
        ],
        // libmagic does not always recognize CSV
        '#^(text/(csv|x-csv|x-comma-separated-values)|application/csv)$#' => [
            'text/plain',
        ],
        // Illustrator files are PDF files
        '#^(application/(illustrator|postscript)|image/pdf)$#' => [
            'application/pdf',
        ],
    ];

    /**
     * @throws ForbiddenContentException
     */
    public function detectFromPath(string $path): string
    {
        return $this->detectFromBuffer(self::readHead($path));
    }

    /**
     * The first HEAD_SIZE bytes of the file.
     */
    public static function readHead(string $path): string
    {
        $handle = @fopen($path, 'rb');
        if (false === $handle) {
            throw new \RuntimeException(sprintf('Cannot open file "%s"', $path));
        }

        try {
            $head = fread($handle, self::HEAD_SIZE);
        } finally {
            fclose($handle);
        }

        return false === $head ? '' : $head;
    }

    /**
     * @param string $head the first bytes of the file (HEAD_SIZE are enough)
     *
     * @throws ForbiddenContentException
     */
    public function detectFromBuffer(string $head): string
    {
        $this->assertNotForbidden($head);

        $type = new \finfo(FILEINFO_MIME_TYPE)->buffer($head);

        return InlineContentTypePolicy::normalize(false === $type ? null : $type) ?? self::UNKNOWN_TYPE;
    }

    /**
     * Detects the type of the file and checks it is consistent with the declared one.
     *
     * @return string the detected type
     *
     * @throws ForbiddenContentException
     * @throws ContentTypeMismatchException
     */
    public function checkFile(string $path, ?string $declaredType): string
    {
        return $this->assertConsistent($declaredType, $this->detectFromPath($path));
    }

    /**
     * @return string the detected type
     *
     * @throws ForbiddenContentException
     * @throws ContentTypeMismatchException
     */
    public function checkBuffer(string $head, ?string $declaredType): string
    {
        return $this->assertConsistent($declaredType, $this->detectFromBuffer($head));
    }

    private function assertConsistent(?string $declaredType, string $detectedType): string
    {
        if (!$this->isConsistent($declaredType, $detectedType)) {
            throw new ContentTypeMismatchException($declaredType, $detectedType);
        }

        return $detectedType;
    }

    /**
     * A file declared without type, or as an opaque binary, claims nothing the content could contradict.
     */
    public function isConsistent(?string $declaredType, string $detectedType): bool
    {
        $declared = InlineContentTypePolicy::normalize($declaredType);
        if (null === $declared || self::UNKNOWN_TYPE === $declared) {
            return true;
        }

        $detected = InlineContentTypePolicy::normalize($detectedType);
        if (null === $detected) {
            return false;
        }

        if ($declared === $detected || $this->isContainerOf($declared, $detected)) {
            return true;
        }

        return self::getFamily($declared) === self::getFamily($detected);
    }

    /**
     * The type that best describes a consistent file: the detected one, unless
     * libmagic only knows its container (or nothing at all), then the declared one.
     */
    public function resolveType(?string $declaredType, string $detectedType): string
    {
        $declared = InlineContentTypePolicy::normalize($declaredType);
        $detected = InlineContentTypePolicy::normalize($detectedType) ?? self::UNKNOWN_TYPE;
        if (null === $declared || self::UNKNOWN_TYPE === $declared) {
            return $detected;
        }

        if (self::UNKNOWN_TYPE === $detected || $this->isContainerOf($declared, $detected)) {
            return $declared;
        }

        return $detected;
    }

    private function isContainerOf(string $declared, string $detected): bool
    {
        foreach (self::CONTAINER_TYPES as $declaredPattern => $containerTypes) {
            if (1 === preg_match($declaredPattern, $declared) && in_array($detected, $containerTypes, true)) {
                return true;
            }
        }

        return false;
    }

    public static function getFamily(?string $type): string
    {
        $type = InlineContentTypePolicy::normalize($type);
        if (null === $type) {
            return self::FAMILY_OTHER;
        }

        if (str_starts_with($type, 'image/') || in_array($type, self::IMAGE_TYPES, true)) {
            return self::FAMILY_IMAGE;
        }

        if (str_starts_with($type, 'video/')
            || str_starts_with($type, 'audio/')
            || in_array($type, self::AUDIO_VIDEO_TYPES, true)) {
            return self::FAMILY_AUDIO_VIDEO;
        }

        if (in_array($type, self::DOCUMENT_TYPES, true)) {
            return self::FAMILY_DOCUMENT;
        }

        foreach (self::DOCUMENT_TYPE_PREFIXES as $prefix) {
            if (str_starts_with($type, $prefix)) {
                return self::FAMILY_DOCUMENT;
            }
        }

        return self::FAMILY_OTHER;
    }

    /**
     * Media tools (ffmpeg) read the files or URLs listed by these formats.
     *
     * @throws ForbiddenContentException
     */
    private function assertNotForbidden(string $head): void
    {
        if (1 === preg_match('~^(?:\xEF\xBB\xBF)?\s*(#EXTM3U|ffconcat\s+version)~i', substr($head, 0, 1024), $matches)) {
            throw new ForbiddenContentException(sprintf('Forbidden content: the file is a %s', str_starts_with(strtolower($matches[1]), '#extm3u') ? 'HLS playlist' : 'ffconcat script'));
        }
    }
}
