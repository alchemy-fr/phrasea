<?php

declare(strict_types=1);

namespace App\Service\Clip;

/**
 * The file could not be cut to its clip: it must not be used in place of the clipped one,
 * as the clip may hide a part of the content.
 */
final class ClipException extends \RuntimeException
{
}
