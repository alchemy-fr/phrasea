<?php

declare(strict_types=1);

namespace App\Attribute;

use Alchemy\MetadataManipulatorBundle\MetadataManipulator;
use Alchemy\StorageBundle\Storage\FileStorageManager;
use App\Entity\Core\Asset;
use App\Entity\Core\Attribute;
use App\Entity\Core\AttributeDefinition;
use Doctrine\ORM\EntityManagerInterface;

class AttributeDataExporter
{
    private EntityManagerInterface $em;
    private FileStorageManager $storageManager;

    public function __construct(EntityManagerInterface $em, FileStorageManager $storageManager)
    {
        $this->em = $em;
        $this->storageManager = $storageManager;
    }

    public function importAttributes(Asset $asset, array $data, ?string $locale): void
    {
        $workspaceId = $asset->getWorkspaceId();
        $repo = $this->em->getRepository(AttributeDefinition::class);

        foreach ($data as $key => $value) {
            $attributeDefinition = $repo->findOneBy([
                'workspace' => $workspaceId,
                'slug' => $key,
            ]);

            if (!$attributeDefinition instanceof AttributeDefinition) {
                continue;
            }

            if ($attributeDefinition->isMultiple()) {
                if (is_array($value)) {
                    foreach ($value as $v) {
                        $this->createAttribute($asset, $attributeDefinition, $v, $locale);
                    }
                }
            } elseif (is_scalar($value)) {
                $this->createAttribute($asset, $attributeDefinition, $value, $locale);
            }
        }

        file_put_contents("/configs/trace.txt", sprintf("%s (%d) asset->getFile()->getPath() = '%s'\n", __FILE__, __LINE__, $asset->getFile()->getPath()), FILE_APPEND);
        file_put_contents("/configs/trace.txt", sprintf("%s (%d) asset->getFile()->getFilename() = '%s'\n", __FILE__, __LINE__, $asset->getFile()->getFilename()), FILE_APPEND);

        if(($tmp = tmpfile()) !== false) {
            file_put_contents("/configs/trace.txt", sprintf("%s (%d) \n", __FILE__, __LINE__), FILE_APPEND);
            $tmpFilename = stream_get_meta_data($tmp)['uri'];
            file_put_contents("/configs/trace.txt", sprintf("%s (%d) tmpFilename = '%s'\n", __FILE__, __LINE__, $tmpFilename), FILE_APPEND);
            $src = $this->storageManager->getStream($asset->getFile()->getPath());
            file_put_contents("/configs/trace.txt", sprintf("%s (%d) \n", __FILE__, __LINE__), FILE_APPEND);
            stream_copy_to_stream($src, $tmp);
            file_put_contents("/configs/trace.txt", sprintf("%s (%d) \n", __FILE__, __LINE__), FILE_APPEND);

            $mm = new MetadataManipulator();
            file_put_contents("/configs/trace.txt", sprintf("%s (%d) \n", __FILE__, __LINE__), FILE_APPEND);
            $meta = $mm->getAllMetadata(new \SplFileObject($tmpFilename));
            file_put_contents("/configs/trace.txt", sprintf("%s (%d) meta: %s \n", __FILE__, __LINE__, var_export($meta, true)), FILE_APPEND);
            fclose($tmp);
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
