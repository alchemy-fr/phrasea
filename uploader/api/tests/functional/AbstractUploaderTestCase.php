<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Alchemy\ApiTest\ApiTestTrait;
use Alchemy\TestBundle\Helper\FixturesTrait;
use ApiPlatform\Test\ApiTestCase;
use App\Databox\DataboxClient;
use App\Databox\DataboxClientInterface;
use App\Entity\Target;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

abstract class AbstractUploaderTestCase extends ApiTestCase
{
    use FixturesTrait;
    use ApiTestTrait;

    protected static ?bool $alwaysBootKernel = true;

    /**
     * The head of a JPEG file: enough for the content check of the completed uploads.
     */
    protected const string JPEG = "\xff\xd8\xff\xe0\x00\x10JFIF\x00\x01\x01\x01\x00H\x00H\x00\x00\xff\xdb\x00C\x00";

    /**
     * Answers the Databox calls of the requests when set.
     */
    protected ?DataboxClientInterface $databox = null;

    protected static function bootKernel(array $options = []): KernelInterface
    {
        return static::bootKernelWithFixtures($options);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        static::disableFixtures();
    }

    /**
     * Sends requests straight to the storage, as the clients do, with the
     * certificate checks of the application (VERIFY_SSL).
     */
    protected static function getStorageClient(): HttpClientInterface
    {
        return static::getContainer()->get('http_client');
    }

    protected function getOrCreateDefaultTarget(): Target
    {
        $em = self::getEntityManager();

        $name = 'TestDefault';
        $slug = 'test-default';
        $target = $em->getRepository(Target::class)->findOneBy([
            'slug' => $slug,
        ]);

        if (!$target instanceof Target) {
            $target = new Target();
            $target->setName($name);
            $target->setSlug($slug);
            $target->setTargetUrl('http://localhost');

            $em->persist($target);
            $em->flush();
        }

        return $target;
    }

    protected function request(?string $accessToken, string $method, string $url, ?array $data = null): ResponseInterface
    {
        $client = static::createClient();
        if (null !== $this->databox && !static::getContainer()->initialized(DataboxClient::class)) {
            static::getContainer()->set(DataboxClient::class, $this->databox);
        }

        $options = [
            'headers' => [
                'Authorization' => $accessToken ? 'Bearer '.$accessToken : null,
            ],
        ];
        if ('PATCH' === $method) {
            $options['headers']['Content-Type'] = 'application/merge-patch+json';
        }
        if (null !== $data) {
            $options['json'] = $data;
        }

        return $client->request($method, $url, $options);
    }
}
