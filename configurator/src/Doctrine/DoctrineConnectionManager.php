<?php

declare(strict_types=1);

namespace App\Doctrine;

use Doctrine\DBAL\Connection;

final readonly class DoctrineConnectionManager
{
    private array $connections;

    public function __construct(
        Connection $authConnection,
        Connection $databoxConnection,
        Connection $exposeConnection,
        Connection $notifyConnection,
        Connection $uploaderConnection,
    )
    {
        $this->connections = [
            'auth' => $authConnection,
            'databox' => $databoxConnection,
            'expose' => $exposeConnection,
            'notify' => $notifyConnection,
            'uploader' => $uploaderConnection,
        ];
    }

    public function getConnection(string $application): Connection
    {
        return $this->connections[$application];
    }
}
