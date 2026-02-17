<?php

namespace App\Exception;

use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;

class ClientExceptionWrapperException extends \RuntimeException implements ExceptionInterface
{
}
