<?php

declare(strict_types=1);

namespace App\Contact;

use App\Entity\Contact;
use Doctrine\ORM\EntityManagerInterface;

class ContactManager
{
    /**
     * @var EntityManagerInterface
     */
    private $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function getContact(string $userId): ?Contact
    {
        return $this->em
            ->getRepository(Contact::class)
            ->findOneBy(['userId' => $userId]);
    }

    public function createContact(string $userId, array $data): Contact
    {
        $contact = new Contact($userId);
        $this->persistContact($contact, $data);

        return $contact;
    }

    public function updateContact(Contact $contact, array $data): void
    {
        $this->persistContact($contact, $data);
    }

    private function persistContact(Contact $contact, array $data): void
    {
        $contact->setEmail($data['email'] ?? null);
        $contact->setPhone($data['phone'] ?? null);
        $contact->setLocale($data['locale'] ?? null);

        $this->em->persist($contact);
        $this->em->flush();
    }
}
