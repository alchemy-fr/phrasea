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
        $container = static::getContainer() ?? $kernel->getContainer();

        $indexes = [
            'asset',
            'collection',
            'asset_data_template',
            'basket',
        ];
        self::$documentIndices = [];
        foreach ($indexes as $indexName) {
            /* @var Index $documentIndices */
            self::$documentIndices[$indexName] = $container->get('fos_elastica.index.'.$indexName);
        }

        static::populateSearchIndices();

        $container->get(EntityManagerInterface::class)->clear();
    }

    /**
     * Rebuilds every index from the database.
     *
     * Entities written straight through the entity manager and only later related
     * through an HTTP request lose their deferred index operation, because the test
     * client reboots the kernel in between. Repopulating is the reliable way to get
     * such a fixture fully indexed.
     */
    protected static function populateSearchIndices(): void
    {
        $application = new Application(static::$kernel);
        $application->setAutoExit(false);
        $application->setCatchExceptions(false);
        $application->run(new ArrayInput([
            'command' => 'fos:elastica:populate',
        ]), new NullOutput());

        self::forceNewEntitiesToBeIndexed();

        foreach (array_keys(self::$documentIndices) as $index) {
            static::waitForESIndex($index);
        }
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
