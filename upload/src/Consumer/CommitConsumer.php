<?php

declare(strict_types=1);

namespace App\Consumer;

use App\Entity\Asset;
use App\Entity\BulkData;
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
    ) {
        $this->client = $client;
        $this->em = $em;
        $this->phraseanetAccessToken = $phraseanetAccessToken;
    }

    protected function doExecute(array $message): int
    {
        $commit = Commit::fromArray($message);

        $bulkData = $this
            ->em
            ->getRepository(BulkData::class)
            ->getBulkDataArray();

        $formData = array_merge($commit->getFormData(), $bulkData);

        $this
            ->em
            ->getRepository(Asset::class)
            ->attachFormData($commit->getFiles(), $formData);

        $this->client->post('/api/v1/upload/enqueue/', [
            'headers' => [
                'Authorization' => 'OAuth '.$this->phraseanetAccessToken,
            ],
            'json' => [
                'assets' => $commit->getFiles(),
                'publisher' => $commit->getUserId(),
            ],
        ]);

        return ConsumerInterface::MSG_ACK;
    }
}
