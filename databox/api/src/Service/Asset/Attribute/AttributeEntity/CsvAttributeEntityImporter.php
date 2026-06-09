<?php

declare(strict_types=1);

namespace App\Service\Asset\Attribute\AttributeEntity;

use App\Entity\Core\AttributeEntity;
use App\Entity\Core\EntityList;
use App\Repository\Core\AttributeEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final readonly class CsvAttributeEntityImporter implements AttributeEntityImporterInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private AttributeEntityRepository $attributeEntityRepository,
        private ValidatorInterface $validator,
    ) {
    }

    public static function getName(): string
    {
        return 'csv';
    }

    public function import(EntityList $entityList, string $data): void
    {
        $rows = str_getcsv($data, "\n")
                |> (fn (array $data): array => array_map('str_getcsv', $data))
                |> (fn (array $data): array => array_filter($data, fn ($v) => !empty($v)));

        $headers = array_shift($rows);

        $locales = [
            'fr',
            'es',
            'it',
            'de',
            'pt',
            'ru',
            'zh',
            'ja',
            'ko',
        ];

        $allowedHeaders = [
            'id',
            'value',
            'external_id',
            'emoji',
            'color',
            'status',
            ...$locales,
        ];

        foreach ($headers as $header) {
            if (!in_array($header, $allowedHeaders, true)) {
                throw new \InvalidArgumentException(sprintf('Unsupported header "%s" in CSV data', $header));
            }
        }
        if (!in_array('value', $headers, true)) {
            throw new \InvalidArgumentException(sprintf('Missing required header "value" in CSV data'));
        }

        $errors = [];

        foreach ($rows as $r => $row) {
            if (false !== $k = array_search('id', $headers, true)) {
                $v = $row[$k];
                /** @var AttributeEntity|null $entity */
                $entity = $this->attributeEntityRepository->find(trim($v));
                if (null === $entity) {
                    throw new \InvalidArgumentException(sprintf('Attribute entity with ID "%s" not found', $v));
                }
                if ($entity->getListId() !== $entityList->getId()) {
                    throw new \InvalidArgumentException(sprintf('Attribute entity with ID "%s" does not belong to list', $v));
                }
            } elseif (false !== $k = array_search('external_id', $headers, true)) {
                $v = $row[$k];
                /** @var AttributeEntity|null $entity */
                $entity = $this->attributeEntityRepository->findOneBy([
                    'listId' => $entityList->getId(),
                    'externalId' => $v,
                ]);
                if (null === $entity) {
                    throw new \InvalidArgumentException(sprintf('Attribute entity with external ID "%s" not found', $v));
                }
            } else {
                $k = array_search('value', $headers, true);
                $v = $row[$k];
                $entity = $this->attributeEntityRepository->findOneBy([
                    'listId' => $entityList->getId(),
                    'value' => $v,
                ]);
                if (!$entity instanceof AttributeEntity) {
                    $entity = new AttributeEntity();
                    $entity->setList($entityList);
                }
            }

            $translations = null;

            foreach ($headers as $index => $header) {
                $v = trim($row[$index]) ?: null;

                if (in_array($header, $locales, true)) {
                    $translations ??= [];
                    $translations[$header] = $v;
                } else {
                    switch ($header) {
                        case 'value':
                            $entity->setValue($v);
                            break;
                        case 'external_id':
                            $entity->setExternalId($v);
                            break;
                        case 'emoji':
                            $entity->setEmoji($v ?: null);
                            break;
                        case 'color':
                            $entity->setColor($v ?: null);
                            break;
                        case 'status':
                            $entity->setStatus((int) $v);
                            break;
                    }
                }
            }

            if (null !== $translations) {
                $entity->setTranslations($translations);
            }

            $violations = $this->validator->validate($entity);
            if ($violations->count() > 0) {
                foreach ($violations as $violation) {
                    $errors[] = sprintf('Line %d: %s: %s', $r + 2, $violation->getPropertyPath(), $violation->getMessage());
                }
            }

            if (!empty($errors)) {
                $this->em->persist($entity);
            }
        }

        if (!empty($errors)) {
            throw new BadRequestHttpException(sprintf("CSV data contains validation errors:\n%s", implode("\n", $errors)));
        }

        $this->em->flush();
    }
}
