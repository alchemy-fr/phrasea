<?php

declare(strict_types=1);

namespace App\Integration;

/**
 * Groups the integrations in the catalog presented to the users.
 */
enum IntegrationCategory: string
{
    case Processing = 'processing';
    case Ai = 'ai';
    case Editor = 'editor';
    case Publication = 'publication';
    case Ingest = 'ingest';
    case Automation = 'automation';
    case Other = 'other';
}
