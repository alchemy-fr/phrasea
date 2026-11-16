<?php

declare(strict_types=1);

namespace Alchemy\RenditionFactory\RemoteExec;

/**
 * Frames exchanged between convert-exec (in the worker) and convert-server (in the conversion
 * container): a type byte, the payload length (32 bits, big endian), the payload.
 *
 * The client sends one REQUEST frame (JSON: tool, args, cwd, timeout), then only waits: the
 * server answers with STDOUT and STDERR frames as the tool writes, then a single EXIT frame
 * (the exit code, once the job directory has been cleaned up) or ERROR frame (the request was
 * refused or the job could not run). Closing the connection on either side ends the job.
 */
final class Protocol
{
    /** TMPDIR of the worker: what it hands to the tools must be there, on the shared volume */
    public const string WORKER_DIR = '/var/convert/worker';
    /** One directory per remote command, on the same volume, so that files are linked, not copied */
    public const string JOBS_DIR = '/var/convert/jobs';

    public const int DEFAULT_PORT = 9000;
    public const int DEFAULT_TIMEOUT = 3600;

    public const string REQUEST = 'R';
    public const string STDOUT = 'O';
    public const string STDERR = 'E';
    public const string EXIT = 'X';
    public const string ERROR = 'F';

    public const int MAX_PAYLOAD = 4 * 1024 * 1024;

    private const int HEADER_SIZE = 5;

    public static function encode(string $type, string $payload): string
    {
        if (1 !== strlen($type)) {
            throw new \InvalidArgumentException('A frame type is a single byte');
        }
        if (strlen($payload) > self::MAX_PAYLOAD) {
            throw new \InvalidArgumentException('Frame payload too large');
        }

        return $type.pack('N', strlen($payload)).$payload;
    }

    /**
     * Writes a whole frame, or fails.
     *
     * @param resource $stream
     */
    public static function write($stream, string $type, string $payload): void
    {
        $data = self::encode($type, $payload);
        while ('' !== $data) {
            $written = @fwrite($stream, $data);
            if (false === $written || 0 === $written) {
                throw new RemoteExecException('Connection lost while writing');
            }
            $data = substr($data, $written);
        }
    }

    /**
     * @param resource $stream
     */
    public static function writeChunked($stream, string $type, string $payload): void
    {
        foreach (str_split($payload, self::MAX_PAYLOAD) as $chunk) {
            self::write($stream, $type, $chunk);
        }
    }

    /**
     * Splits buffered data into frames.
     *
     * @return list<array{string, string}> the complete frames, removed from the buffer
     */
    public static function decode(string &$buffer): array
    {
        $frames = [];
        while (strlen($buffer) >= self::HEADER_SIZE) {
            $length = unpack('N', substr($buffer, 1, 4))[1];
            if ($length > self::MAX_PAYLOAD) {
                throw new RemoteExecException('Frame payload too large');
            }
            if (strlen($buffer) < self::HEADER_SIZE + $length) {
                break;
            }
            $frames[] = [$buffer[0], substr($buffer, self::HEADER_SIZE, $length)];
            $buffer = substr($buffer, self::HEADER_SIZE + $length);
        }

        return $frames;
    }
}
