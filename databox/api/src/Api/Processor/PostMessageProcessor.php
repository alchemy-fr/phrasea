<?php

declare(strict_types=1);

namespace App\Api\Processor;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use Alchemy\CoreBundle\Util\DoctrineUtil;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Api\Model\Input\ThreadMessageInput;
use App\Entity\Discussion\Message;
use App\Entity\Discussion\Thread;
use App\Repository\Discussion\ThreadRepository;
use App\Security\Voter\AbstractVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class PostMessageProcessor implements ProcessorInterface
{
    use SecurityAwareTrait;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly MessageBusInterface $bus,
        private readonly ThreadRepository $threadRepository,
    ) {
    }

    /**
     * @param ThreadMessageInput $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = []): Message
    {
        $user = $this->getStrictUser();

        if ($threadId = $data->threadId) {
            $thread = DoctrineUtil::findStrictByRepo($this->threadRepository, $threadId);
        } else {
            $thread = $this->threadRepository->findOneBy([
                'key' => $data->threadKey,
            ]);

            if (null === $thread) {
                $thread = new Thread();
                $thread->setKey($data->threadKey);
                $this->em->persist($thread);
            }
        }

        $this->denyAccessUnlessGranted(AbstractVoter::EDIT, $thread);

        $message = new Message();
        $message->setThread($thread);
        $message->setAuthorId($user->getId());
        $message->setContent($data->content);
        $this->em->persist($message);
        $this->em->flush();

        return $message;
    }
}
