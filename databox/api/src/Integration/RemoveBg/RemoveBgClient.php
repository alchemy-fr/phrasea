<?php

declare(strict_types=1);

namespace App\Integration\RemoveBg;

use Alchemy\CoreBundle\Listener\ClientExceptionListener;
use App\Entity\Core\File;
use App\Service\Asset\FileFetcher;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;
use Symfony\Contracts\HttpClient\HttpClientInterface;

readonly class RemoveBgClient
{
    /**
     * Largest image remove.bg accepts.
     */
    private const int MAX_IMAGE_BYTES = 22 * 1024 * 1024;

    public function __construct(
        private FileFetcher $fileFetcher,
        private string $cacheDir,
        private HttpClientInterface $removeBgClient,
        private ClientExceptionListener $clientExceptionListener,
    ) {
    }

    public function getBgRemoved(File $file, string $apiKey): string
    {
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0777, true);
        }

        $path = $this->fileFetcher->getFile($file);
        $md5 = md5_file($path).'-v2';

        $cacheFile = sprintf('%s/%s', $this->cacheDir, $md5);
        if (file_exists($cacheFile)) {
            return $cacheFile;
        }

        $size = filesize($path);
        if (false === $size || $size > self::MAX_IMAGE_BYTES) {
            throw new \InvalidArgumentException(sprintf('Image is %s bytes: remove.bg accepts at most %d bytes.', false === $size ? 'unknown' : (string) $size, self::MAX_IMAGE_BYTES));
        }

        // The multipart body is built in memory: curl can only resend a string body when a
        // kept-alive connection closed by the server must be retried.
        $formData = new FormDataPart([
            'image_file' => DataPart::fromPath($path),
            'size' => 'auto',
        ]);

        return $this->clientExceptionListener->wrapClientRequest(function () use ($formData, $apiKey, $cacheFile): string {
            $res = $this->removeBgClient->request('POST', '/v1.0/removebg', [
                'headers' => [
                    'X-Api-Key' => $apiKey,
                    ...$formData->getPreparedHeaders()->toArray(),
                ],
                'body' => $formData->bodyToString(),
            ]);

            try {
                $fileHandler = fopen($cacheFile, 'w');
                foreach ($this->removeBgClient->stream($res) as $chunk) {
                    fwrite($fileHandler, $chunk->getContent());
                }
                fclose($fileHandler);
            } catch (\Throwable $e) {
                if (isset($fileHandler)) {
                    fclose($fileHandler);
                    @unlink($cacheFile);
                }

                throw $e;
            }

            return $cacheFile;
        });
    }
}
