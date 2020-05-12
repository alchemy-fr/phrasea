<?php

declare(strict_types=1);

namespace App\Tests;

use Alchemy\ApiTest\ApiTestCase;
use App\Entity\Publication;
use Doctrine\ORM\EntityManagerInterface;
use Hautelook\AliceBundle\PhpUnit\ReloadDatabaseTrait;

abstract class AbstractUploaderTestCase extends ApiTestCase
{
    use ReloadDatabaseTrait;
}
