<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\FormSchema;
use Doctrine\ORM\EntityManagerInterface;

class FormSchemaManager
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function loadSchema(string $targetId, ?string $locale): array
    {
        $formSchema = $this
            ->em
            ->getRepository(FormSchema::class)
            ->getSchemaForLocale($targetId, $locale);

        if (null === $formSchema) {
            return [];
        }

        return $formSchema->getData();
    }
}
