<?php

declare(strict_types=1);

namespace App\Attribute;

use App\Entity\Core\Asset;
use App\Entity\Core\Attribute;
use App\Entity\Core\AttributeDefinition;
use App\Entity\Core\Tag;
use App\Repository\Core\TagRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class AttributeDataExporter
{
    private const string BUILT_IN_ATTRIBUTE_PREFIX = 'databox_';

    public function __construct(
        private EntityManagerInterface $em,
        private TagRepository $tagRepository,
    ) {
    }

    public function importAttributes(Asset $asset, array $data, ?string $locale): void
    {
        $workspaceId = $asset->getWorkspaceId();
        $repo = $this->em->getRepository(AttributeDefinition::class);

        foreach ($data as $key => $value) {
            if (str_starts_with($key, self::BUILT_IN_ATTRIBUTE_PREFIX)) {
                $k = substr($key, strlen(self::BUILT_IN_ATTRIBUTE_PREFIX));
                switch ($k) {
                    case 'tags':
                        if (is_array($value)) {
                            foreach ($value as $tagId) {
                                $t = $this->tagRepository->findOneBy([
                                    'id' => $tagId,
                                    'workspace' => $workspaceId,
                                ]);
                                if ($t instanceof Tag) {
                                    $asset->addTag($t);
                                }
                            }
                        }
                        break;
                    case 'title':
                        if (is_string($value)) {
                            $asset->setTitle($value);
                        }
                        break;
                }
                continue;
            }

            $fieldLocale = $locale;
            if (1 === preg_match('#^(.+):([a-z_-]{2,5})$#i', $key, $matches)) {
                $key = $matches[1];
                $fieldLocale = $matches[2];
            }

            $attributeDefinition = $repo->findOneBy([
                'workspace' => $workspaceId,
                'slug' => $key,
            ]);

            if (!$attributeDefinition instanceof AttributeDefinition) {
                continue;
            }

            if (!$attributeDefinition->isTranslatable()) {
                $fieldLocale = null;
            }

            if ($attributeDefinition->isMultiple()) {
                if (is_array($value)) {
                    foreach ($value as $v) {
                        $this->createAttribute($asset, $attributeDefinition, $v, $fieldLocale);
                    }
                }
            } elseif (is_scalar($value)) {
                $this->createAttribute($asset, $attributeDefinition, $value, $fieldLocale);
            }
        }
    }

    private function createAttribute(Asset $asset, AttributeDefinition $definition, $value, ?string $locale): void
    {
        if (null === $value) {
            return;
        }
        $value = (string) $value;

        $attribute = new Attribute();
        $attribute->setAsset($asset);
        $attribute->setDefinition($definition);
        $attribute->setOrigin(Attribute::ORIGIN_HUMAN);
        $attribute->setValue($value);
        $attribute->setLocale($locale);

        $this->em->persist($attribute);
    }
}
