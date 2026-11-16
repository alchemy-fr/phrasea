<?php

declare(strict_types=1);

namespace Alchemy\RenditionFactory\Tests\RemoteExec;

use Alchemy\RenditionFactory\RemoteExec\ConverterTools;
use Alchemy\RenditionFactory\RemoteExec\Protocol;
use Alchemy\RenditionFactory\RemoteExec\RemoteExecException;
use Alchemy\RenditionFactory\RemoteExec\Request;
use Alchemy\RenditionFactory\RemoteExec\Tool;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ProtocolTest extends TestCase
{
    public function testFramesAreDecodedOnceComplete(): void
    {
        $data = Protocol::encode(Protocol::STDOUT, 'out').Protocol::encode(Protocol::STDERR, '').Protocol::encode(Protocol::EXIT, '3');

        $buffer = substr($data, 0, 10);
        $this->assertSame([[Protocol::STDOUT, 'out']], Protocol::decode($buffer));
        $buffer .= substr($data, 10);
        $this->assertSame([[Protocol::STDERR, ''], [Protocol::EXIT, '3']], Protocol::decode($buffer));
        $this->assertSame('', $buffer);
    }

    public function testOversizedFrameIsRefused(): void
    {
        $buffer = Protocol::STDOUT.pack('N', Protocol::MAX_PAYLOAD + 1);

        $this->expectException(RemoteExecException::class);

        Protocol::decode($buffer);
    }

    public function testRequestKeepsBinaryArguments(): void
    {
        $request = new Request(Tool::Exiftool, ["-XMP-dc:Title=\xff\xfe caf\xc3\xa9", '/var/convert/jobs/abc/0/in.jpg'], '/var/convert/jobs/abc', 60);

        $decoded = Request::fromJson($request->toJson(), 3600);

        $this->assertEquals($request, $decoded);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function provideInvalidRequests(): iterable
    {
        $valid = ['tool' => 'ffmpeg', 'args' => [base64_encode('-version')], 'cwd' => '/var/convert/jobs/a', 'timeout' => 60];

        yield 'tool outside of the whitelist' => [['tool' => 'sh'] + $valid];
        yield 'tool path' => [['tool' => '/usr/bin/ffmpeg'] + $valid];
        yield 'timeout above the maximum' => [['timeout' => 3601] + $valid];
        yield 'no timeout' => [['timeout' => 0] + $valid];
        yield 'timeout as string' => [['timeout' => '60'] + $valid];
        yield 'arguments not a list' => [['args' => ['a' => base64_encode('x')]] + $valid];
        yield 'argument not base64' => [['args' => ['%%%']] + $valid];
        yield 'NUL in an argument' => [['args' => [base64_encode("a\0b")]] + $valid];
        yield 'no job directory' => [['cwd' => ''] + $valid];
    }

    /**
     * @param array<string, mixed> $data
     */
    #[DataProvider('provideInvalidRequests')]
    public function testInvalidRequestIsRefused(array $data): void
    {
        $this->expectException(RemoteExecException::class);

        Request::fromJson(json_encode($data), 3600);
    }

    public function testMalformedRequestIsRefused(): void
    {
        $this->expectException(RemoteExecException::class);

        Request::fromJson('{', 3600);
    }

    public function testToolsRunLocallyWithoutConverterUrl(): void
    {
        foreach ([null, ''] as $url) {
            $tools = new ConverterTools($url);
            $this->assertFalse($tools->isRemote());
            $this->assertNull($tools->getBinary(Tool::Ffmpeg));
            $this->assertSame([], $tools->getEnvironment());
            $this->assertSame([], $tools->getFFMpegConfiguration());
        }
    }

    public function testToolsRunThroughTheWrappersWithConverterUrl(): void
    {
        $tools = new ConverterTools('tcp://databox-converter:9000');

        $this->assertTrue($tools->isRemote());
        $this->assertSame('/usr/local/libexec/convert/soffice', $tools->getBinary(Tool::Soffice));
        $this->assertSame(['CONVERTER_URL' => 'tcp://databox-converter:9000'], $tools->getEnvironment());
        $this->assertSame([
            'ffmpeg.binaries' => '/usr/local/libexec/convert/ffmpeg',
            'ffprobe.binaries' => '/usr/local/libexec/convert/ffprobe',
        ], $tools->getFFMpegConfiguration());
    }
}
