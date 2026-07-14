<?php

namespace Alchemy\RenditionFactory\Image;

/*
 * $detector = new ColorspaceDetector();
 *
 * echo $detector->detect('/path/to/photo.jpg');   // e.g. "YCbCr" or "sRGB"
 *
 * print_r($detector->inspect('/path/to/photo.jpg'));
 * // [
 * //   'colorspace' => 'CMYK',
 * //   'method'     => 'imagick',
 * //   'format'     => 'image/jpeg',
 * // ]
 */
final readonly class ColorspaceDetector
{
    public const string CS_RGB = 'rgb';
    public const string CS_SRGB = 'srgb';
    public const string CS_CMYK = 'cmyk';
    public const string CS_GRAYSCALE = 'grayscale';
    public const string CS_YCBCR = 'ycbcr';
    public const string CS_YCCK = 'ycck';
    public const string CS_PALETTE = 'indexed';
    public const string CS_LAB = 'cielab';
    public const string CS_UNKNOWN = 'unknown';

    /**
     * Detect the colorspace of the given image file.
     *
     * @param string $path path to the image file
     *
     * @return string one of the CS_* constants
     *
     * @throws \InvalidArgumentException if the file does not exist or is unreadable
     */
    public function detect(string $path): string
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new \InvalidArgumentException("File not found or not readable: {$path}");
        }

        // Prefer Imagick — it understands real colorspaces and ICC profiles.
        if (extension_loaded('imagick')) {
            $result = $this->detectWithImagick($path);
            if (self::CS_UNKNOWN !== $result) {
                return $result;
            }
        }

        // Fall back to header parsing.
        return $this->detectFromHeaders($path);
    }

    /**
     * Convenience helper: returns detailed information rather than just a string.
     *
     * @return array{colorspace:string, method:string, format:?string}
     */
    public function inspect(string $path): array
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new \InvalidArgumentException("File not found or not readable: {$path}");
        }

        if (extension_loaded('imagick')) {
            $cs = $this->detectWithImagick($path);
            if (self::CS_UNKNOWN !== $cs) {
                return [
                    'colorspace' => $cs,
                    'method' => 'imagick',
                    'format' => $this->detectFormat($path),
                ];
            }
        }

        return [
            'colorspace' => $this->detectFromHeaders($path),
            'method' => 'headers',
            'format' => $this->detectFormat($path),
        ];
    }

    private function detectWithImagick(string $path): string
    {
        try {
            $img = new \Imagick();
            // ping reads only the header — fast and memory-cheap.
            $img->pingImage($path);
            $cs = $img->getImageColorspace();
            $img->clear();
        } catch (\Throwable $e) {
            return self::CS_UNKNOWN;
        }

        // Map Imagick colorspace constants to our normalised values.
        $map = [
            \Imagick::COLORSPACE_RGB => self::CS_RGB,
            \Imagick::COLORSPACE_SRGB => self::CS_SRGB,
            \Imagick::COLORSPACE_CMYK => self::CS_CMYK,
            \Imagick::COLORSPACE_GRAY => self::CS_GRAYSCALE,
            \Imagick::COLORSPACE_YCBCR => self::CS_YCBCR,
            \Imagick::COLORSPACE_YCC => self::CS_YCBCR,
            \Imagick::COLORSPACE_LAB => self::CS_LAB,
        ];

        // Some Imagick builds also expose a linear-RGB constant.
        if (defined('Imagick::COLORSPACE_RGBLINEAR')) {
            $map[\Imagick::COLORSPACE_RGBLINEAR] = self::CS_RGB;
        }

        return $map[$cs] ?? self::CS_UNKNOWN;
    }

    private function detectFromHeaders(string $path): string
    {
        $fh = @fopen($path, 'rb');
        if (false === $fh) {
            return self::CS_UNKNOWN;
        }

        $magic = fread($fh, 12);
        if (false === $magic || strlen($magic) < 2) {
            fclose($fh);

            return self::CS_UNKNOWN;
        }

        try {
            // JPEG: FF D8
            if ("\xFF\xD8" === substr($magic, 0, 2)) {
                return $this->parseJpeg($fh);
            }
            // PNG: 89 50 4E 47 0D 0A 1A 0A
            if ("\x89PNG\r\n\x1A\n" === substr($magic, 0, 8)) {
                return $this->parsePng($fh);
            }
            // GIF: "GIF87a" or "GIF89a" — always palette based.
            if ('GIF' === substr($magic, 0, 3)) {
                return self::CS_PALETTE;
            }
            // BMP: "BM" — device-dependent RGB.
            if ('BM' === substr($magic, 0, 2)) {
                return self::CS_RGB;
            }
            // WebP: "RIFF"...."WEBP"
            if ('RIFF' === substr($magic, 0, 4) && 'WEBP' === substr($magic, 8, 4)) {
                return self::CS_YCBCR; // WebP encodes in YCbCr internally.
            }
        } finally {
            fclose($fh);
        }

        return self::CS_UNKNOWN;
    }

    /**
     * Parse a JPEG by walking its marker segments to find the Start-Of-Frame
     * (SOFn) marker, whose component count reveals the colorspace.
     *
     * @param resource $fh file handle positioned just after the SOI marker
     */
    private function parseJpeg($fh): string
    {
        // We already consumed the first 12 bytes into $magic, so reposition
        // right after the SOI marker (FF D8).
        fseek($fh, 2);

        while (!feof($fh)) {
            // Find the next marker: skip until we hit 0xFF followed by non-0xFF.
            $byte = fread($fh, 1);
            if ('' === $byte || false === $byte) {
                break;
            }
            if ("\xFF" !== $byte) {
                continue;
            }

            // Skip any fill bytes (multiple 0xFF).
            do {
                $marker = fread($fh, 1);
            } while ("\xFF" === $marker);

            if ('' === $marker || false === $marker) {
                break;
            }

            $m = ord($marker);

            // Standalone markers without a length field.
            // RSTn (D0–D7), SOI (D8), EOI (D9), TEM (01).
            if (($m >= 0xD0 && $m <= 0xD9) || 0x01 === $m) {
                if (0xD9 === $m) { // EOI
                    break;
                }
                continue;
            }

            // Read the 2-byte segment length (includes the length bytes).
            $lenBytes = fread($fh, 2);
            if (false === $lenBytes || strlen($lenBytes) < 2) {
                break;
            }
            $len = (ord($lenBytes[0]) << 8) | ord($lenBytes[1]);
            if ($len < 2) {
                break;
            }

            // SOF markers: C0–CF, excluding DHT (C4), DAC (CC) and RSTn.
            $isSof = ($m >= 0xC0 && $m <= 0xCF && 0xC4 !== $m && 0xC8 !== $m && 0xCC !== $m);

            if ($isSof) {
                // SOF payload: precision(1) height(2) width(2) components(1) ...
                $payload = fread($fh, 6);
                if (false === $payload || strlen($payload) < 6) {
                    return self::CS_UNKNOWN;
                }
                $components = ord($payload[5]);

                switch ($components) {
                    case 1:
                        return self::CS_GRAYSCALE;
                    case 3:
                        // 3 components is almost always YCbCr (JFIF default),
                        // which is decoded to RGB for display.
                        return self::CS_YCBCR;
                    case 4:
                        // 4 components is CMYK or YCCK (distinguished by the
                        // Adobe APP14 transform flag, not read here).
                        return self::CS_CMYK;
                    default:
                        return self::CS_UNKNOWN;
                }
            }

            // Not an SOF: skip the rest of this segment.
            fseek($fh, $len - 2, SEEK_CUR);
        }

        return self::CS_UNKNOWN;
    }

    /**
     * Parse a PNG IHDR chunk. The color-type byte determines the colorspace.
     *
     * @param resource $fh file handle (position is irrelevant; we seek)
     */
    private function parsePng($fh): string
    {
        // IHDR is always the first chunk, starting at byte offset 8.
        // Layout: length(4) "IHDR"(4) width(4) height(4) bitdepth(1) colortype(1)
        // The color-type byte therefore sits at offset 25.
        fseek($fh, 25);
        $byte = fread($fh, 1);
        if ('' === $byte || false === $byte) {
            return self::CS_UNKNOWN;
        }

        $colorType = ord($byte);

        switch ($colorType) {
            case 0: // grayscale
            case 4: // grayscale + alpha
                return self::CS_GRAYSCALE;
            case 2: // truecolor
            case 6: // truecolor + alpha
                return self::CS_RGB;
            case 3: // indexed palette
                return self::CS_PALETTE;
            default:
                return self::CS_UNKNOWN;
        }
    }

    private function detectFormat(string $path): ?string
    {
        $info = @getimagesize($path);
        if (false === $info || !isset($info[2])) {
            return null;
        }
        $type = image_type_to_mime_type($info[2]);

        return $type ?: null;
    }
}
