<?php

declare(strict_types=1);

namespace App\Api\Processor;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use Alchemy\CoreBundle\Util\DoctrineUtil;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Api\Model\Input\EditThreadMessageInput;
use App\Entity\Discussion\Message;
use App\Repository\Discussion\MessageRepository;
use App\Security\Voter\AbstractVoter;
use App\Service\Discussion\DiscussionPusher;
use Doctrine\ORM\EntityManagerInterface;

class PutMessageProcessor implements ProcessorInterface
{
    use SecurityAwareTrait;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly MessageRepository $messageRepository,
        private readonly DiscussionPusher $discussionPusher,
    ) {
    }

    /**
     * @param EditThreadMessageInput $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = []): Message
    {
        /** @var Message $message */
        $message = DoctrineUtil::findStrictByRepo($this->messageRepository, $uriVariables['id']);

        $this->denyAccessUnlessGranted(AbstractVoter::EDIT, $message);

        $message->setContent($data->content);
        $this->em->persist($message);
        $this->em->flush();

        $this->discussionPusher->dispatchMessageToThread($message);

        return $message;
    }
}
