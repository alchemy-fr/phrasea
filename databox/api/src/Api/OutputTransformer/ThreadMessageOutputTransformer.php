<?php

declare(strict_types=1);

namespace App\Api\OutputTransformer;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use App\Api\Model\Output\ThreadMessageOutput;
use App\Api\Traits\UserLocaleTrait;
use App\Entity\Discussion\Message;
use App\Security\Voter\AbstractVoter;
use Doctrine\ORM\EntityManagerInterface;

class ThreadMessageOutputTransformer implements OutputTransformerInterface
{
    use SecurityAwareTrait;
    use UserOutputTransformerTrait;
    use UserLocaleTrait;
    use GroupsHelperTrait;

    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function supports(string $outputClass, object $data): bool
    {
        return ThreadMessageOutput::class === $outputClass && $data instanceof Message;
    }

    /**
     * @param Message $data
     */
    public function transform(object $data, string $outputClass, array &$context = []): object
    {
        $output = new ThreadMessageOutput();
        $output->setCreatedAt($data->getCreatedAt());
        $output->setUpdatedAt($data->getUpdatedAt());
        $output->setId($data->getId());

        $output->content = $data->getContent();
        $output->attachments = $data->getAttachments();
        $output->thread = $data->getThread();

        if ($this->hasGroup([
            Message::GROUP_LIST,
            Message::GROUP_READ,
        ], $context)) {
            $output->author = $this->transformUser($data->getAuthorId());
            $output->capabilities = [
                'canEdit' => $this->isGranted(AbstractVoter::EDIT, $data),
                'canDelete' => $this->isGranted(AbstractVoter::DELETE, $data),
            ];
        }

        return $output;
    }
}
