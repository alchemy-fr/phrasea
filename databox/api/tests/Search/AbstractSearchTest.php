<?php

declare(strict_types=1);

namespace App\Tests\Search;

use App\Tests\AbstractDataboxTestCase;
use Elastica\Index;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\HttpKernel\TerminableInterface;

abstract class AbstractSearchTest extends AbstractDataboxTestCase
{
    protected static array $documentIndices = [];

    protected static function bootKernel(array $options = []): KernelInterface
    {
        static::ensureKernelTestCase();
        $kernel = parent::bootKernel($options);

        $container = static::getContainer() ?? static::$kernel->getContainer();

        $indexes = [
            'asset',
            'collection',
        ];
        self::$documentIndices = [];
        foreach ($indexes as $indexName) {
            /* @var Index $documentIndices */
            self::$documentIndices[$indexName] = $container->get('fos_elastica.index.'.$indexName);
            self::$documentIndices[$indexName]->deleteByQuery('*:*');
        }

        $application = new Application($kernel);
        $application->setAutoExit(false);
        $application->run(new ArrayInput([
            'command' => 'fos:elastica:populate',
        ]), new NullOutput());

        self::forceNewEntitiesToBeIndexed();

        foreach ($indexes as $index) {
            static::waitForESIndex($index);
        }

        return $kernel;
    }

    protected static function forceNewEntitiesToBeIndexed(): void
    {
        $kernel = static::$kernel;
        if ($kernel instanceof TerminableInterface) {
            // Force kernel terminate in order to sync ES indices
            $kernel->terminate(new Request(), new Response());
        }
    }

    protected static function waitForESIndex(string $indexName): void
    {
        self::$documentIndices[$indexName]->refresh();
    }
}
