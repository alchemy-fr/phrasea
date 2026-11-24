<?php

declare(strict_types=1);

namespace App\Tests\Unit\Rendition;

use Alchemy\RenditionFactory\Transformer\Video\FFMpeg\RestrictedProcessBuilderFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RestrictedProcessBuilderFactoryTest extends TestCase
{
    private const array WHITELIST = ['-protocol_whitelist', 'file,pipe'];

    #[DataProvider('getFFMpegCommands')]
    public function testFFMpegInputsAreRestricted(array $command, array $expected): void
    {
        $factory = new RestrictedProcessBuilderFactory(PHP_BINARY);

        $this->assertSame($expected, $factory->restrictArguments($command));
    }

    public static function getFFMpegCommands(): iterable
    {
        yield 'each input' => [
            ['-y', '-i', 'a', '-i', 'b', 'out'],
            ['-y', ...self::WHITELIST, '-i', 'a', ...self::WHITELIST, '-i', 'b', 'out'],
        ];
        yield 'after the other input options' => [
            ['-f', 'concat', '-safe', '0', '-i', 'list.txt', '-c', 'copy', 'out.mp4'],
            ['-f', 'concat', '-safe', '0', ...self::WHITELIST, '-i', 'list.txt', '-c', 'copy', 'out.mp4'],
        ];
        yield 'already whitelisted input' => [
            ['-protocol_whitelist', 'file', '-i', 'a', '-i', 'b', 'out'],
            ['-protocol_whitelist', 'file', '-i', 'a', ...self::WHITELIST, '-i', 'b', 'out'],
        ];
        yield 'input named like an option' => [
            ['-i', '-i', 'out'],
            [...self::WHITELIST, '-i', '-i', 'out'],
        ];
        yield 'no input' => [
            ['-version'],
            ['-version'],
        ];
    }

    #[DataProvider('getFFProbeCommands')]
    public function testFFProbeInputIsRestricted(array $command, array $expected): void
    {
        $factory = new RestrictedProcessBuilderFactory(PHP_BINARY, probe: true);

        $this->assertSame($expected, $factory->restrictArguments($command));
    }

    public static function getFFProbeCommands(): iterable
    {
        yield 'path first' => [
            ['/tmp/file', '-show_streams', '-print_format', 'json'],
            [...self::WHITELIST, '/tmp/file', '-show_streams', '-print_format', 'json'],
        ];
        yield 'path as input option' => [
            ['-loglevel', 'error', '-show_entries', 'stream_side_data=rotation', '-i', '/tmp/file'],
            [...self::WHITELIST, '-loglevel', 'error', '-show_entries', 'stream_side_data=rotation', '-i', '/tmp/file'],
        ];
        yield 'help' => [
            ['-help', '-loglevel', 'quiet'],
            ['-help', '-loglevel', 'quiet'],
        ];
        yield 'version' => [
            ['-version'],
            ['-version'],
        ];
        yield 'already whitelisted' => [
            ['-protocol_whitelist', 'file', '/tmp/file', '-show_format'],
            ['-protocol_whitelist', 'file', '/tmp/file', '-show_format'],
        ];
    }

    public function testCreatedProcessIsRestricted(): void
    {
        $factory = new RestrictedProcessBuilderFactory(PHP_BINARY);
        $factory->setTimeout(42);

        $process = $factory->create(['-i', 'in.mp4', 'out.mp4']);

        $this->assertSame(
            implode(' ', [PHP_BINARY, ...self::WHITELIST, '-i', 'in.mp4', 'out.mp4']),
            str_replace("'", '', $process->getCommandLine()),
        );
        $this->assertSame(42.0, $process->getTimeout());
    }
}
