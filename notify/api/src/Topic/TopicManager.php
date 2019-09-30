<?php

declare(strict_types=1);

namespace App\Topic;

use App\Entity\Contact;
use App\Entity\TopicSubscriber;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class TopicManager
{
    /**
     * @var EntityManagerInterface
     */
    private $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function addSubscriber(Contact $contact, string $topic): void
    {
        $topicSubscription = $this->em->getRepository(TopicSubscriber::class)
            ->findOneBy([
                'contact' => $contact->getId(),
                'topic' => $topic,
            ]);

        if (null !== $topicSubscription) {
            return;
        }

        $topicSubscription = new TopicSubscriber();
        $topicSubscription->setContact($contact);
        $topicSubscription->setTopic($topic);
        $this->em->persist($topicSubscription);
        $this->em->flush();
    }

    public function getContactById(string $id): Contact
    {
        $contact = $this->em->find(Contact::class, $id);

        if (null === $contact) {
            throw new NotFoundHttpException(sprintf('Contact %s not found', $id));
        }

        return $contact;
    }

    public function removeSubscriber(Contact $contact, string $topic): void
    {
        $topicSubscription = $this->em
            ->getRepository(TopicSubscriber::class)
            ->findOneBy([
                'contact' => $contact->getId(),
                'topic' => $topic,
            ]);

        if (null === $topicSubscription) {
            return;
        }

        $this->em->remove($topicSubscription);
        $this->em->flush();
    }

    /**
     * @return TopicSubscriber[]
     */
    public function getSubscriptions(string $topic): array
    {
        return $this->em
            ->getRepository(TopicSubscriber::class)
            ->findBy([
                'topic' => $topic,
            ]);
    }
}
