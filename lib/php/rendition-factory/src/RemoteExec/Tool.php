<?php

declare(strict_types=1);

namespace Alchemy\RenditionFactory\RemoteExec;

/**
 * The external tools that the conversion container runs, each bound to a fixed binary of its image.
 */
enum Tool: string
{
    case Soffice = 'soffice';
    case Ffmpeg = 'ffmpeg';
    case Ffprobe = 'ffprobe';
    case Exiftool = 'exiftool';

    private const string BLACKHOLE_PROXY = 'http://127.0.0.1:9';

    /**
     * The binary in the conversion container.
     */
    public function getBinary(): string
    {
        return match ($this) {
            self::Soffice => '/usr/bin/soffice',
            self::Ffmpeg => '/usr/bin/ffmpeg',
            self::Ffprobe => '/usr/bin/ffprobe',
            self::Exiftool => '/opt/exiftool/exiftool',
        };
    }

    /**
     * Variables added to the minimal environment of the tool (PATH, LANG, HOME, TMPDIR).
     *
     * @return array<string, string>
     */
    public function getEnvironment(): array
    {
        return match ($this) {
            // Same settings as a local conversion (PdfConverter): no display, no proxy that works
            self::Soffice => [
                'SAL_USE_VCLPLUGIN' => 'svp',
                'http_proxy' => self::BLACKHOLE_PROXY,
                'https_proxy' => self::BLACKHOLE_PROXY,
                'ftp_proxy' => self::BLACKHOLE_PROXY,
                'all_proxy' => self::BLACKHOLE_PROXY,
                'HTTP_PROXY' => self::BLACKHOLE_PROXY,
                'HTTPS_PROXY' => self::BLACKHOLE_PROXY,
                'FTP_PROXY' => self::BLACKHOLE_PROXY,
                'ALL_PROXY' => self::BLACKHOLE_PROXY,
            ],
            default => [],
        };
    }
}
