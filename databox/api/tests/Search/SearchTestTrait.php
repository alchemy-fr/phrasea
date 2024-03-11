<?php

declare(strict_types=1);

namespace App\Tests\Search;

use Doctrine\ORM\EntityManagerInterface;
use Elastica\Index;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\HttpKernel\TerminableInterface;

trait SearchTestTrait
{
    protected static array $documentIndices = [];

    protected static function bootSearch(KernelInterface $kernel): void
    {
        $container = static::getContainer() ?? static::$kernel->getContainer();

        $indexes = [
            'asset',
            'collection',
            'attribute',
            'asset_data_template',
        ];
        self::$documentIndices = [];
        foreach ($indexes as $indexName) {
            /* @var Index $documentIndices */
            self::$documentIndices[$indexName] = $container->get('fos_elastica.index.'.$indexName);
            self::$documentIndices[$indexName]->deleteByQuery('*:*');
        }

        $application = new Application($kernel);
        $application->setAutoExit(false);
        $application->setCatchExceptions(false);
        $application->run(new ArrayInput([
            'command' => 'fos:elastica:populate',
        ]), new NullOutput());

        self::forceNewEntitiesToBeIndexed();

        foreach ($indexes as $index) {
            static::waitForESIndex($index);
        }

        $container->get(EntityManagerInterface::class)->clear();
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
