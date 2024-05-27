<?php

declare(strict_types=1);

namespace App\Entity\Integration;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Api\Model\Output\IntegrationDataOutput;
use App\Api\Provider\IntegrationDataProvider;
use App\Entity\Core\File;
use Doctrine\ORM\Mapping as ORM;

#[ApiResource(
    shortName: 'integration-file-data',
    operations: [
        new Get(security: 'is_granted("READ", object)'),
        new Delete(security: 'is_granted("DELETE", object)'),
        new Put(security: 'is_granted("EDIT", object)'),
        new Post(securityPostDenormalize: 'is_granted("CREATE", object)'),
    ],
    normalizationContext: [
        'groups' => [AbstractIntegrationData::GROUP_LIST],
    ],
    output: IntegrationDataOutput::class
)]
#[ApiResource(
    uriTemplate: '/integrations/{integrationId}/file-data',
    shortName: 'integration-file-data',
    operations: [
        new GetCollection(
            provider: IntegrationDataProvider::class,
        ),
    ],
    uriVariables: [
        'integrationId' => new Link(
            toProperty: 'integration',
            fromClass: WorkspaceIntegration::class
        ),
    ],
    normalizationContext: [
        'groups' => [AbstractIntegrationData::GROUP_LIST],
    ],
)]
#[ORM\Entity]
#[ORM\Index(columns: ['integration_id', 'object_id', 'name'], name: 'int_obj_name_idx')]
class IntegrationFileData extends AbstractIntegrationData
{
    #[ORM\ManyToOne(targetEntity: File::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?File $object = null;

    /**
     * @return File|null
     */
    public function getObject(): ?object
    {
        return $this->object;
    }

    /**
     * @param File|null $object
     */
    public function setObject(?object $object): void
    {
        $this->object = $object;
    }
}
