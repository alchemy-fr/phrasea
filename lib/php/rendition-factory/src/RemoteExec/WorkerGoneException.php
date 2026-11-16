<?php

declare(strict_types=1);

namespace Alchemy\RenditionFactory\RemoteExec;

/**
 * The worker hung up (its command was killed, timed out on its side…), or the server is stopping.
 */
final class WorkerGoneException extends RemoteExecException
{
}
