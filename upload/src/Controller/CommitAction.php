<?php

declare(strict_types=1);

namespace App\Controller;

use ApiPlatform\Core\Metadata\Resource\Factory\ResourceMetadataFactoryInterface;
use ApiPlatform\Core\Validator\ValidatorInterface;
use App\Entity\Asset;
use App\Model\Commit;
use App\Model\User;
use App\Storage\AssetManager;
use App\Storage\FileStorageManager;
use OldSound\RabbitMqBundle\RabbitMq\ProducerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

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
