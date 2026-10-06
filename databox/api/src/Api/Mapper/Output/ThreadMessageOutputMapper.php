<?php

declare(strict_types=1);

namespace App\Api\Mapper\Output;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use App\Api\Model\Output\ThreadMessageOutput;
use App\Api\Traits\UserLocaleTrait;
use App\Entity\Discussion\Message;
use App\Security\Voter\AbstractVoter;
use App\Service\Discussion\MessageAttachmentManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(index: ThreadMessageOutput::class)]
class ThreadMessageOutputMapper implements OutputMapperInterface
{
    use SecurityAwareTrait;
    use UserOutputTrait;
    use UserLocaleTrait;
    use GroupsHelperTrait;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly MessageAttachmentManager $attachmentManager,
    ) {
    }

    public function supports(object $data): bool
    {
        return $data instanceof Message;
    }

    /**
     * @param Message $data
     */
    public function map(object $data, array $context = []): object
    {
        $output = new ThreadMessageOutput();
        $output->setCreatedAt($data->getCreatedAt());
        $output->setUpdatedAt($data->getUpdatedAt());
        $output->setId($data->getId());

        $output->content = $data->getContent();
        $output->attachments = $this->attachmentManager->resolveAttachments($data->getAttachments(), $data->getThread());
        $output->thread = $data->getThread();

        if ($this->hasGroup([
            Message::GROUP_LIST,
            Message::GROUP_READ,
        ], $context)) {
            $output->author = $this->transformUser($data->getAuthorId());
            $output->capabilities = [
                'edit' => $this->isGranted(AbstractVoter::EDIT, $data),
                'delete' => $this->isGranted(AbstractVoter::DELETE, $data),
            ];
        }

        return $output;
    }
}
