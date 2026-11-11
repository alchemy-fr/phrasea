<?php

declare(strict_types=1);

namespace App\Tests\Unit\Mcp;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Stands for the application kernel: records the sub-requests the MCP tools send
 * to the REST API and answers them with queued responses.
 */
final class RecordingHttpKernel implements HttpKernelInterface
{
    /**
     * @var list<array{request: Request, type: int}>
     */
    public array $calls = [];

    /**
     * @var list<Response>
     */
    private array $responses = [];

    public function willRespond(mixed $data, int $status = 200): self
    {
        $this->responses[] = null === $data ? new Response('', $status) : new JsonResponse($data, $status);

        return $this;
    }

    public function handle(Request $request, int $type = self::MAIN_REQUEST, bool $catch = true): Response
    {
        $this->calls[] = ['request' => $request, 'type' => $type];

        if ([] === $this->responses) {
            throw new \LogicException(sprintf('No response queued for %s %s', $request->getMethod(), $request->getRequestUri()));
        }

        return array_shift($this->responses);
    }

    public function lastRequest(): Request
    {
        return $this->calls[array_key_last($this->calls)]['request'];
    }

    public function lastBody(): ?array
    {
        $content = $this->lastRequest()->getContent();

        return '' === $content ? null : json_decode($content, true, 512, JSON_THROW_ON_ERROR);
    }
}
