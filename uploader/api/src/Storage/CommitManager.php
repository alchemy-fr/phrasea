<?php

declare(strict_types=1);

namespace App\Storage;

use App\Entity\Asset;
use App\Entity\AssetRepository;
use App\Entity\Commit;
use Doctrine\ORM\EntityManagerInterface;

readonly class CommitManager
{
    public function __construct(
        private EntityManagerInterface $em,
        private AssetManager $assetManager,
    ) {
    }

    /**
     * Stores the commit with its files, the data of its target merged over the values entered.
     *
     * A file belongs to one commit only: when another commit took one of them meanwhile (the same files
     * committed twice at once), nothing is stored.
     *
     * @return bool false when nothing was stored
     */
    public function store(Commit $commit): bool
    {
        $commit->generateToken();
        $target = $commit->getTarget();

        $commit->setFormLocale($commit->getLocale());
        $commit->setTotalSize($this->assetManager->getTotalSize($commit->getFiles()));

        $formData = array_merge($commit->getFormData(), $target->getData() ?? []);
        if (!isset($formData['collection_destination']) && null !== $target->getDefaultDestination()) {
            $formData['collection_destination'] = $target->getDefaultDestination();
        }
        $commit->setFormData($formData);

        /** @var AssetRepository $assetRepository */
        $assetRepository = $this->em->getRepository(Asset::class);

        $this->em->beginTransaction();
        try {
            $this->em->persist($commit);
            $this->em->flush();
            $attached = $assetRepository->attachCommit($commit->getFiles(), $commit->getId());
            if ($attached !== count($commit->getFiles())) {
                $this->em->rollback();
                $this->em->detach($commit);

                return false;
            }

            $this->em->commit();
        } catch (\Throwable $e) {
            $this->em->rollback();
            throw $e;
        }

        return true;
    }
}
