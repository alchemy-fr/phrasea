<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\FormSchema;
use Doctrine\ORM\EntityManagerInterface;

class FormSchemaManager
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
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
