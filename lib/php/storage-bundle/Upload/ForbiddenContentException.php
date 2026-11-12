<?php

declare(strict_types=1);

namespace Alchemy\StorageBundle\Upload;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * The file content is refused whatever its declared type: a format that
 * makes a media tool read other files or URLs (HLS playlist, ffconcat script).
 */
final class ForbiddenContentException extends BadRequestHttpException
{
}
