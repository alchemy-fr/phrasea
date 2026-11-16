<?php

declare(strict_types=1);

namespace Alchemy\RenditionFactory\RemoteExec;

/**
 * convert-exec <tool> <args…>: runs the tool in the conversion container (CONVERTER_URL).
 * Exits with the code of the tool, or 125 when the command could not run there.
 */
final class ClientCommand
{
    private const int SIGHUP = 1;
    private const int SIGINT = 2;
    private const int SIGTERM = 15;

    /**
     * @param list<string>          $argv
     * @param array<string, string> $env
     */
    public static function main(array $argv, array $env): int
    {
        $tool = Tool::tryFrom($argv[1] ?? '');
        if (null === $tool) {
            fwrite(STDERR, sprintf("Usage: %s <%s> [args…]\n", basename($argv[0] ?? 'convert-exec'), implode('|', array_map(static fn (Tool $t): string => $t->value, Tool::cases()))));

            return Client::EXIT_FAILURE;
        }

        $url = $env['CONVERTER_URL'] ?? '';
        if ('' === $url) {
            fwrite(STDERR, "convert-exec: CONVERTER_URL is not set\n");

            return Client::EXIT_FAILURE;
        }

        try {
            $client = new Client($url);

            if (function_exists('pcntl_async_signals')) {
                pcntl_async_signals(true);
                foreach ([self::SIGHUP, self::SIGINT, self::SIGTERM] as $signal) {
                    pcntl_signal($signal, $client->abort(...));
                }
            }

            return $client->run($tool, array_slice($argv, 2), STDOUT, STDERR);
        } catch (RemoteExecException $e) {
            fwrite(STDERR, sprintf("convert-exec %s: %s\n", $tool->value, $e->getMessage()));

            return Client::EXIT_FAILURE;
        }
    }
}
