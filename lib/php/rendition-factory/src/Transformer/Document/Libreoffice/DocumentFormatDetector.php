<?php

declare(strict_types=1);

namespace Alchemy\RenditionFactory\Transformer\Document\Libreoffice;

/**
 * Tells the format of a document from its content, never from its name or its declared type:
 * whoever edits a rendition definition can point it at any file of the workspace.
 */
final readonly class DocumentFormatDetector
{
    private const string ZIP_SIGNATURE = "PK\x03\x04";
    private const string OLE_SIGNATURE = "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1";
    private const string RTF_SIGNATURE = '{\rtf';
    private const string PDF_SIGNATURE = '%PDF-';

    private const int MAX_MANIFEST_SIZE = 1024 * 1024;

    private const array OLE_TYPES = [
        'application/msword' => DocumentFormat::Doc,
        'application/vnd.ms-excel' => DocumentFormat::Xls,
        'application/vnd.ms-powerpoint' => DocumentFormat::Ppt,
    ];

    private const array OPEN_DOCUMENT_TYPES = [
        'application/vnd.oasis.opendocument.text' => DocumentFormat::Odt,
        'application/vnd.oasis.opendocument.spreadsheet' => DocumentFormat::Ods,
        'application/vnd.oasis.opendocument.presentation' => DocumentFormat::Odp,
    ];

    private const array OOXML_MAIN_PART_TYPES = [
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml' => DocumentFormat::Docx,
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml' => DocumentFormat::Xlsx,
        'application/vnd.openxmlformats-officedocument.presentationml.presentation.main+xml' => DocumentFormat::Pptx,
    ];

    public function isPdf(string $path): bool
    {
        return str_starts_with($this->readHeader($path), self::PDF_SIGNATURE);
    }

    /**
     * @return DocumentFormat|null null when the content is not one of the accepted formats
     */
    public function detect(string $path): ?DocumentFormat
    {
        $header = $this->readHeader($path);

        if (str_starts_with($header, self::ZIP_SIGNATURE)) {
            return $this->detectZipFormat($path);
        }

        if (str_starts_with($header, self::OLE_SIGNATURE)) {
            return self::OLE_TYPES[$this->getMimeType($path)] ?? null;
        }

        if (str_starts_with($header, self::RTF_SIGNATURE)) {
            return DocumentFormat::Rtf;
        }

        if ('text/plain' === $this->getMimeType($path)) {
            return DocumentFormat::Text;
        }

        return null;
    }

    private function detectZipFormat(string $path): ?DocumentFormat
    {
        $zip = new \ZipArchive();
        if (true !== $zip->open($path, \ZipArchive::RDONLY)) {
            return null;
        }

        try {
            $mimetype = $this->readZipEntry($zip, 'mimetype');
            if (null !== $mimetype) {
                return self::OPEN_DOCUMENT_TYPES[trim($mimetype)] ?? null;
            }

            $contentTypes = $this->readZipEntry($zip, '[Content_Types].xml');
            if (null === $contentTypes) {
                return null;
            }

            return $this->detectOoxmlFormat($contentTypes);
        } finally {
            $zip->close();
        }
    }

    private function detectOoxmlFormat(string $contentTypes): ?DocumentFormat
    {
        $document = new \DOMDocument();
        if (!@$document->loadXML($contentTypes, LIBXML_NONET)) {
            return null;
        }

        $formats = [];
        foreach ($document->getElementsByTagName('Override') as $override) {
            $format = self::OOXML_MAIN_PART_TYPES[$override->getAttribute('ContentType')] ?? null;
            if (null !== $format) {
                $formats[$format->value] = $format;
            }
        }

        return 1 === count($formats) ? reset($formats) : null;
    }

    private function readZipEntry(\ZipArchive $zip, string $name): ?string
    {
        $stat = $zip->statName($name);
        if (false === $stat || $stat['size'] > self::MAX_MANIFEST_SIZE) {
            return null;
        }

        $content = $zip->getFromName($name);

        return false === $content ? null : $content;
    }

    private function readHeader(string $path): string
    {
        $handle = @fopen($path, 'rb');
        if (false === $handle) {
            throw new \RuntimeException(sprintf('Cannot read file "%s"', basename($path)));
        }

        try {
            return (string) fread($handle, 8);
        } finally {
            fclose($handle);
        }
    }

    private function getMimeType(string $path): string
    {
        return (string) (new \finfo(FILEINFO_MIME_TYPE))->file($path);
    }
}
