<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\FormSchema;
use Doctrine\ORM\EntityManagerInterface;

class FormSchemaManager
{
    private EntityManagerInterface $em;
    private string $defaultSchemaFile;

    public function __construct(EntityManagerInterface $em, string $defaultSchemaFile)
    {
        $this->em = $em;
        $this->defaultSchemaFile = $defaultSchemaFile;
    }

    public function loadSchema(?string $locale): array
    {
        $formSchema = $this
            ->em
            ->getRepository(FormSchema::class)
            ->getSchemaForLocale($locale);

        if (null === $formSchema) {
            return json_decode(file_get_contents($this->defaultSchemaFile), true);
        }

        return $formSchema->getData();
    }

    public function persistSchema(?string $locale, array $schema): void
    {
        $this
            ->em
            ->getRepository(FormSchema::class)
            ->persistSchema($locale, $schema);
    }
}
