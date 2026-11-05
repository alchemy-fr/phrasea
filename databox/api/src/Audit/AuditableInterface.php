<?php

declare(strict_types=1);

namespace App\Audit;

/**
 * Marks the entities whose creation, update and deletion are written to the audit log
 * by the AuditLogListener. The class must be in the "alchemy_track" entities map.
 */
interface AuditableInterface
{
    public function getId(): string;
}
