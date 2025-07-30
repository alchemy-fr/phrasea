<?php

declare(strict_types=1);

namespace App\Consumer\Handler;

use App\Entity\Asset;
use App\Entity\Commit;
use App\Entity\FormSchema;
use App\Entity\TargetParams;
use App\Storage\AssetManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final readonly class CommitHandler
{
    public function __construct(
        private MessageBusInterface $bus,
        private AssetManager $assetManager,
        private EntityManagerInterface $em,
    ) {
    }

    public function __invoke(CommitMessage $message): void
    {
        $commit = Commit::fromMessage($message, $this->em);
        $commit->generateToken();
        $target = $commit->getTarget();

        if (null !== $commit->schemaId) {
            $formSchema = $this->em
                ->getRepository(FormSchema::class)
                ->find($commit->schemaId);

            if ($formSchema instanceof FormSchema) {
                switch ($formSchema->getLocaleMode()) {
                    case FormSchema::LOCALE_MODE_NO_LOCALE:
                        $commit->setFormLocale(null);
                        break;
                    case FormSchema::LOCALE_MODE_USE_UA:
                        $commit->setFormLocale($message->getLocale());
                        break;
                    case FormSchema::LOCALE_MODE_FORCED:
                        $commit->setFormLocale($formSchema->getLocale());
                        break;
                }
            }
        }

        $totalSize = $this->assetManager->getTotalSize($commit->getFiles());
        $commit->setTotalSize($totalSize);

        $targetParams = $this->em
            ->getRepository(TargetParams::class)
            ->findOneBy([
                'target' => $commit->getTarget()->getId(),
            ]);
        $targetData = $targetParams ? $targetParams->getData() : [];

        $formData = array_merge($commit->getFormData(), $targetData);
        if (!isset($formData['collection_destination']) && null !== $target->getDefaultDestination()) {
            $formData['collection_destination'] = $target->getDefaultDestination();
        }
        $commit->setFormData($formData);

        $this->em->beginTransaction();
        try {
            $this->em->persist($commit);
            $this->em->flush();
            $this->em
                ->getRepository(Asset::class)
                ->attachCommit($commit->getFiles(), $commit->getId());

            $this->em->commit();
        } catch (\Throwable $e) {
            $this->em->rollback();
            throw $e;
        }

        $this->em->clear();
        $this->bus->dispatch(new AssetConsumerNotify($commit->getId()));
    }
}
