<?php

declare(strict_types=1);

namespace Alchemy\RenditionFactory\RemoteExec;

final readonly class Request
{
    private const int MAX_ARGS = 4096;

    /**
     * @param list<string> $args
     */
    public function __construct(
        public Tool $tool,
        public array $args,
        public string $cwd,
        public int $timeout,
    ) {
    }

    /**
     * The arguments are base64 encoded: they are bytes (metadata values written by exiftool…),
     * not always valid UTF-8.
     */
    public function toJson(): string
    {
        return json_encode([
            'tool' => $this->tool->value,
            'args' => array_map(base64_encode(...), $this->args),
            'cwd' => $this->cwd,
            'timeout' => $this->timeout,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Only checks the shape and the bounds: the job directory is checked on the file system by
     * JobDirectory.
     *
     * @throws RemoteExecException
     */
    public static function fromJson(string $json, int $maxTimeout): self
    {
        try {
            $data = json_decode($json, true, 4, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new RemoteExecException('Malformed request', 0, $e);
        }
        if (!is_array($data)) {
            throw new RemoteExecException('Malformed request');
        }

        $tool = is_string($data['tool'] ?? null) ? Tool::tryFrom($data['tool']) : null;
        if (null === $tool) {
            throw new RemoteExecException(sprintf('Tool not allowed, expected one of: %s', implode(', ', array_map(static fn (Tool $t): string => $t->value, Tool::cases()))));
        }

        $encodedArgs = $data['args'] ?? null;
        if (!is_array($encodedArgs) || !array_is_list($encodedArgs) || count($encodedArgs) > self::MAX_ARGS) {
            throw new RemoteExecException('Invalid arguments');
        }
        $args = [];
        foreach ($encodedArgs as $encodedArg) {
            $arg = is_string($encodedArg) ? base64_decode($encodedArg, true) : false;
            if (false === $arg || str_contains($arg, "\0")) {
                throw new RemoteExecException('Invalid arguments');
            }
            $args[] = $arg;
        }

        $cwd = $data['cwd'] ?? null;
        if (!is_string($cwd) || '' === $cwd) {
            throw new RemoteExecException('Missing job directory');
        }

        $timeout = $data['timeout'] ?? null;
        if (!is_int($timeout) || $timeout < 1 || $timeout > $maxTimeout) {
            throw new RemoteExecException(sprintf('Invalid timeout: an integer between 1 and %d is expected', $maxTimeout));
        }

        return new self($tool, $args, $cwd, $timeout);
    }
}
