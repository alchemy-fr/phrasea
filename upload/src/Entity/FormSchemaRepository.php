<?php

declare(strict_types=1);

namespace App\Entity;

use DateTime;
use Doctrine\ORM\EntityRepository;

class FormSchemaRepository extends EntityRepository
{
    public function getSchemaForLocale(?string $locale): ?FormSchema
    {
        return $this->createQueryBuilder('fs')
            ->select('fs')
            ->andWhere('fs.locale = :locale OR fs.locale IS NULL')
            ->setParameters([
                'locale' => $locale,
            ])
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function persistSchema(?string $locale, string $jsonData): void
    {
        if (null === $schema = $this->getSchemaForLocale($locale)) {
            $schema = new FormSchema();
            $schema->setLocale($locale);
        }

        $schema->setData($jsonData);
        $schema->setUpdatedAt(new DateTime());

        $this->_em->persist($schema);
        $this->_em->flush();
    }
}
