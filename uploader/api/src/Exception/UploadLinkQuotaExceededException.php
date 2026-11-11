<?php

declare(strict_types=1);

namespace App\Exception;

use ApiPlatform\Metadata\Exception\ProblemExceptionInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * The file would go over a limit of the upload link it is sent through.
 */
final class UploadLinkQuotaExceededException extends BadRequestHttpException implements ProblemExceptionInterface
{
    final public const string CODE = 'upload_link_quota_exceeded';

    public function __construct()
    {
        parent::__construct('Upload link quota exceeded');
    }

    public function getType(): string
    {
        return '/errors/'.self::CODE;
    }

    public function getTitle(): ?string
    {
        return 'Upload link quota exceeded';
    }

    public function getStatus(): ?int
    {
        return $this->getStatusCode();
    }

    public function getDetail(): ?string
    {
        return $this->getMessage();
    }

    public function getInstance(): ?string
    {
        return null;
    }
}
