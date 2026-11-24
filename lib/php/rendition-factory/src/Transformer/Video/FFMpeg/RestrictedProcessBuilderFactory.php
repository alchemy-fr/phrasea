<?php

declare(strict_types=1);

namespace Alchemy\RenditionFactory\Transformer\Video\FFMpeg;

use Alchemy\BinaryDriver\ProcessBuilderFactory;

/**
 * Limits the protocols that ffmpeg and ffprobe may open to local files and pipes, so that a forged
 * input (HLS playlist, reference to a URL…) cannot make them read from the network.
 *
 * The whitelist is an input option, which only applies to the next input: ffmpeg gets it before
 * each "-i", ffprobe (a single input) at the head of the command. Informational commands
 * (-help, -version…) are left as is.
 */
final class RestrictedProcessBuilderFactory extends ProcessBuilderFactory
{
    public const string PROTOCOL_WHITELIST_OPTION = '-protocol_whitelist';
    public const string ALLOWED_PROTOCOLS = 'file,pipe';

    private const string INPUT_OPTION = '-i';

    private const array INFORMATIONAL_OPTIONS = [
        '-h', '-?', '-help', '--help', '-version', '-buildconf', '-formats', '-muxers', '-demuxers',
        '-devices', '-codecs', '-decoders', '-encoders', '-bsfs', '-protocols', '-filters',
        '-pix_fmts', '-layouts', '-sample_fmts', '-dispositions', '-colors', '-hwaccels', '-L',
    ];

    /**
     * @param bool $probe whether the binary is ffprobe rather than ffmpeg
     */
    public function __construct(string $binary, private readonly bool $probe = false)
    {
        parent::__construct($binary);
    }

    public function create($arguments = [])
    {
        if (is_array($arguments)) {
            $arguments = $this->restrictArguments($arguments);
        }

        return parent::create($arguments);
    }

    public function restrictArguments(array $arguments): array
    {
        $arguments = array_values($arguments);

        return $this->probe ? $this->restrictProbeArguments($arguments) : $this->restrictInputArguments($arguments);
    }

    private function restrictInputArguments(array $arguments): array
    {
        $restricted = [];
        $whitelisted = false;
        $count = count($arguments);
        for ($i = 0; $i < $count; ++$i) {
            $argument = $arguments[$i];

            if (self::PROTOCOL_WHITELIST_OPTION === $argument) {
                $whitelisted = true;
            } elseif (self::INPUT_OPTION === $argument) {
                if (!$whitelisted) {
                    $restricted[] = self::PROTOCOL_WHITELIST_OPTION;
                    $restricted[] = self::ALLOWED_PROTOCOLS;
                }
                $whitelisted = false;
            } else {
                $restricted[] = $argument;
                continue;
            }

            // Copies the option with its value, which is never read as an option itself
            $restricted[] = $argument;
            if ($i + 1 < $count) {
                $restricted[] = $arguments[++$i];
            }
        }

        return $restricted;
    }

    private function restrictProbeArguments(array $arguments): array
    {
        foreach ($arguments as $argument) {
            if (self::PROTOCOL_WHITELIST_OPTION === $argument || in_array($argument, self::INFORMATIONAL_OPTIONS, true)) {
                return $arguments;
            }
        }

        return [self::PROTOCOL_WHITELIST_OPTION, self::ALLOWED_PROTOCOLS, ...$arguments];
    }
}
