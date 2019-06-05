<?php

declare(strict_types=1);

namespace App\Consumer;

use App\Entity\Asset;
use App\Model\Commit;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Client;
use OldSound\RabbitMqBundle\RabbitMq\ConsumerInterface;

class CommitConsumer extends AbstractConsumer
{
    /**
     * @var Client
     */
    private $client;

    /**
     * @var EntityManagerInterface
     */
    private $em;

    /**
     * @var string
     */
    private $phraseanetAccessToken;

    public function __construct(
        Client $client,
        EntityManagerInterface $em,
        string $phraseanetAccessToken
    )
    {
        $this->client = $client;
        $this->em = $em;
        $this->phraseanetAccessToken = $phraseanetAccessToken;
    }

    protected function doExecute(array $message): int
    {
        $commit = Commit::fromArray($message);

        $this->em->createQueryBuilder()
            ->update(Asset::class, 'a')
            ->set('a.formData', ':data')
            ->andWhere('a.id IN (:ids)')
            ->setParameter('data', json_encode($commit->getFormData()))
            ->setParameter('ids', $commit->getFiles())
            ->getQuery()
            ->execute();

        $this->client->post('/api/v1/upload/enqueue/', [
            'headers' => [
                'Authorization' => 'Bearer '.$this->phraseanetAccessToken,
            ],
            'json' => json_encode([
                'assets' => $commit->getFiles(),
                'publisher' => $commit->getUserId(),
            ]),
        ]);

        return ConsumerInterface::MSG_ACK;
    }
}
