<?php

declare(strict_types=1);

namespace Alchemy\RenditionFactory\RemoteExec;

/**
 * An argument refers to a file of the worker that cannot be given to the conversion container.
 */
final class UnmappablePathException extends RemoteExecException
{
}
