<?php

declare(strict_types=1);

namespace Alchemy\ESBundle\Doctrine;

use Alchemy\ESBundle\Listener\DeferredIndexListener;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsMiddleware;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection as DriverConnection;
use Doctrine\DBAL\Driver\Middleware;
use Doctrine\DBAL\Driver\Middleware\AbstractConnectionMiddleware;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;

/**
 * Tells DeferredIndexListener when a transaction begins, commits or rolls back, so that a rollback
 * discards the index operations scheduled during that transaction only
 * (DBAL 4 no longer dispatches transaction events).
 *
 * Only the outermost transaction reaches the driver: DBAL runs nested ones as savepoints, whose
 * rollback discards nothing.
 */
#[AsMiddleware]
final readonly class TransactionRollbackMiddleware implements Middleware
{
    public function __construct(
        private DeferredIndexListener $deferredIndexListener,
    ) {
    }

    public function wrap(Driver $driver): Driver
    {
        $listener = $this->deferredIndexListener;

        return new class($driver, $listener) extends AbstractDriverMiddleware {
            public function __construct(Driver $driver, private readonly DeferredIndexListener $listener)
            {
                parent::__construct($driver);
            }

            public function connect(#[\SensitiveParameter] array $params): DriverConnection
            {
                return new class(parent::connect($params), $this->listener) extends AbstractConnectionMiddleware {
                    public function __construct(DriverConnection $connection, private readonly DeferredIndexListener $listener)
                    {
                        parent::__construct($connection);
                    }

                    public function beginTransaction(): void
                    {
                        parent::beginTransaction();
                        $this->listener->onTransactionBegin($this);
                    }

                    public function commit(): void
                    {
                        parent::commit();
                        $this->listener->onTransactionCommit($this);
                    }

                    public function rollBack(): void
                    {
                        parent::rollBack();
                        $this->listener->onTransactionRollBack($this);
                    }
                };
            }
        };
    }
}
