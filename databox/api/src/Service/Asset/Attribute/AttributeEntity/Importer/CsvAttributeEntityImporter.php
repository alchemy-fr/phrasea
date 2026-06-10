<?php

declare(strict_types=1);

namespace App\Service\Asset\Attribute\AttributeEntity\Importer;

use App\Entity\Core\AttributeEntity;
use App\Entity\Core\EntityList;
use App\Repository\Core\AttributeEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Nonstandard\Uuid;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final readonly class CsvAttributeEntityImporter implements AttributeEntityImporterInterface
{
    final public const string TRANSLATION_PREFIX = 'translation_';

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
        $rows = str_getcsv($data, separator: "\n", escape: '')
                |> (fn (array $data): array => array_map(fn (string $r) => str_getcsv($r, escape: ''), $data));

        $headers = array_shift($rows);
        if (empty($headers)) {
            throw new BadRequestHttpException('Empty headers');
        }

        $allowedHeaders = [
            'id',
            'value',
            'external_id',
            'emoji',
            'color',
            'status',
        ];
        foreach ($headers as $header) {
            if (!str_starts_with($header, self::TRANSLATION_PREFIX) && !in_array($header, $allowedHeaders, true)) {
                throw new BadRequestHttpException(sprintf('Unsupported header "%s" in CSV data', $header));
            }
        }

        if (!in_array('value', $headers, true)) {
            throw new BadRequestHttpException(sprintf('Missing required header "value" in CSV data'));
        }

        $errors = [];

        $getValue = function (array $row, string $header) use ($headers): string {
            if (false !== $k = array_search($header, $headers, true)) {
                return trim($row[$k] ?? '');
            }

            return '';
        };

        $addError = function (int $row, string $property, string $error) use (&$errors): void {
            $errors[] = sprintf('Line %d: %s: %s', $row + 2, $property, $error);
        };

        foreach ($rows as $r => $row) {
            if ($v = $getValue($row, 'id')) {
                if (!Uuid::isValid($v)) {
                    $addError($r, 'id', sprintf('Invalid UUID "%s"', $v));
                    continue;
                }
                /** @var AttributeEntity|null $entity */
                $entity = $this->attributeEntityRepository->find($v);
                if (null === $entity) {
                    $addError($r, 'id', sprintf('Attribute entity with ID "%s" not found', $v));
                    continue;
                }
                if ($entity->getListId() !== $entityList->getId()) {
                    $addError($r, 'id', sprintf('Attribute entity with ID "%s" does not belong to list', $v));
                    continue;
                }
            } elseif ($v = $getValue($row, 'external_id')) {
                /** @var AttributeEntity|null $entity */
                $entity = $this->attributeEntityRepository->findOneBy([
                    'list' => $entityList->getId(),
                    'externalId' => $v,
                ]);

                if (!$entity instanceof AttributeEntity) {
                    $entity = new AttributeEntity();
                    $entity->setList($entityList);
                    $entity->setExternalId($v);
                }
            } else {
                $v = $getValue($row, 'value');
                $entity = $this->attributeEntityRepository->findOneBy([
                    'list' => $entityList->getId(),
                    'value' => $v,
                ]);
                if (!$entity instanceof AttributeEntity) {
                    $entity = new AttributeEntity();
                    $entity->setList($entityList);
                }
            }

            $translations = null;

            foreach ($headers as $index => $header) {
                $v = trim($row[$index] ?? '');
                if (str_starts_with($header, self::TRANSLATION_PREFIX)) {
                    $translations ??= [];
                    $translations[substr($header, strlen(self::TRANSLATION_PREFIX))] = $v;
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
                            if ('' !== $v) {
                                $entity->setStatus((int) $v);
                            }
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
                    $addError($r, $violation->getPropertyPath(), $violation->getMessage());
                }
            }

            if (empty($errors)) {
                $this->em->persist($entity);
            }
        }

        if (!empty($errors)) {
            throw new BadRequestHttpException(sprintf("CSV data contains validation errors:\n%s", implode("\n", $errors)));
        }

        $this->em->flush();
    }
}
