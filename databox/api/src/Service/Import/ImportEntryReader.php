<?php

declare(strict_types=1);

namespace App\Service\Import;

use App\Entity\Core\AssetImport;
use App\Service\Import\Exception\ImportFileException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Reads the entries of an instructions file.
 *
 * NDJSON is read line by line, whatever its size. JSON (a list of entries or {"assets": [...]})
 * is decoded at once, so its size is limited.
 */
final readonly class ImportEntryReader
{
    private const string UTF8_BOM = "\xEF\xBB\xBF";

    public function __construct(
        #[Autowire(env: 'int:ASSET_IMPORT_JSON_MAX_SIZE')]
        private int $jsonMaxSize,
    ) {
    }

    /**
     * @param resource $stream
     *
     * @return \Generator<int, array|string> the entries by line number (NDJSON) or position from 1 (JSON);
     *                                       the error message of an entry that is not a JSON object
     *
     * @throws ImportFileException
     */
    public function iterate($stream, string $format): \Generator
    {
        return match ($format) {
            AssetImport::FORMAT_NDJSON => $this->iterateNdjson($stream),
            AssetImport::FORMAT_JSON => $this->iterateJson($stream),
            default => throw new ImportFileException(sprintf('Unsupported format "%s"', $format)),
        };
    }

    /**
     * @param resource $stream
     */
    private function iterateNdjson($stream): \Generator
    {
        $line = 0;
        while (false !== $buffer = fgets($stream)) {
            ++$line;
            if (1 === $line && str_starts_with($buffer, self::UTF8_BOM)) {
                $buffer = substr($buffer, strlen(self::UTF8_BOM));
            }

            $buffer = trim($buffer);
            if ('' === $buffer) {
                continue;
            }

            try {
                $entry = json_decode($buffer, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                yield $line => sprintf('Invalid JSON: %s', $e->getMessage());

                continue;
            }

            yield $line => self::isObject($entry) ? $entry : 'The entry must be a JSON object';
        }
    }

    /**
     * @param resource $stream
     */
    private function iterateJson($stream): \Generator
    {
        $content = stream_get_contents($stream, $this->jsonMaxSize + 1);
        if (false === $content) {
            throw new ImportFileException('Cannot read the file');
        }
        if (strlen($content) > $this->jsonMaxSize) {
            throw new ImportFileException(sprintf('The JSON file exceeds %d MB: use the NDJSON format (one entry per line) for larger imports', (int) ceil($this->jsonMaxSize / 1024 / 1024)));
        }

        if (str_starts_with($content, self::UTF8_BOM)) {
            $content = substr($content, strlen(self::UTF8_BOM));
        }

        try {
            $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new ImportFileException(sprintf('Invalid JSON: %s', $e->getMessage()), 0, $e);
        }
        unset($content);

        if (is_array($data) && !array_is_list($data) && array_key_exists('assets', $data)) {
            $data = $data['assets'];
        }
        if (!is_array($data) || !array_is_list($data)) {
            throw new ImportFileException('The JSON file must contain a list of entries, or an object with an "assets" list');
        }

        foreach ($data as $index => $entry) {
            yield $index + 1 => self::isObject($entry) ? $entry : 'The entry must be a JSON object';
        }
    }

    /**
     * Decoded as an associative array, an empty JSON object is an empty list.
     */
    private static function isObject(mixed $entry): bool
    {
        return is_array($entry) && ([] === $entry || !array_is_list($entry));
    }
}
