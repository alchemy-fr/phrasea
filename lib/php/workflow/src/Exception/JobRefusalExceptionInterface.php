<?php

declare(strict_types=1);

namespace Alchemy\Workflow\Exception;

/**
 * An expected refusal of the job (e.g. its input cannot be processed), not a bug: the job ends
 * in failure with the exception message as its error, logged as a warning, without trace.
 */
interface JobRefusalExceptionInterface extends \Throwable
{
}
