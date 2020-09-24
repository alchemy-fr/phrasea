<?php

declare(strict_types=1);

namespace App\Tests;

use Alchemy\ApiTest\ApiTestCase;
use Hautelook\AliceBundle\PhpUnit\ReloadDatabaseTrait;

abstract class AbstractUploaderTestCase extends ApiTestCase
{
    use ReloadDatabaseTrait;
}
