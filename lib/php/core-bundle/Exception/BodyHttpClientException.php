<?php

namespace Alchemy\CoreBundle\Exception;

use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class BodyHttpClientException extends \RuntimeException implements ClientExceptionInterface
{
    public function __construct(
        private readonly ClientExceptionInterface $exception,
    ) {
        $response = $this->exception->getResponse();

        parent::__construct(sprintf(
            "%s with headers: %s\nwith response:\n%s",
            $this->exception->getMessage(),
            json_encode($response->getHeaders(false)),
            $response->getContent(false),
        ), $this->exception->getCode(), $this->exception->getPrevious());
    }

    public function getResponse(): ResponseInterface
    {
        return $this->exception->getResponse();
    }
}
