<?php

declare(strict_types=1);

namespace App\Controller;

use App\Model\Commit;
use App\Model\User;
use App\Storage\AssetManager;
use OldSound\RabbitMqBundle\RabbitMq\ProducerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;

final class CommitAction extends AbstractController
{
    /**
     * @var AssetManager
     */
    private $assetManager;
    /**
     * @var ProducerInterface
     */
    private $commitProducer;

    public function __construct(
        AssetManager $assetManager,
        ProducerInterface $commitProducer
    ) {
        $this->assetManager = $assetManager;
        $this->commitProducer = $commitProducer;
    }

    public function __invoke(Commit $data)
    {
        /** @var User $user */
        $user = $this->getUser();
        $data->setUserId($user->getId());

        $message = json_encode($data->toArray());
        $this->commitProducer->publish($message);

        return new JsonResponse(true);
    }
}
