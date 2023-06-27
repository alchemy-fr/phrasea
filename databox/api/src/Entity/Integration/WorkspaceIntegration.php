<?php

declare(strict_types=1);

namespace App\Entity\Integration;

use ApiPlatform\Core\Annotation\ApiFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\SearchFilter;
use App\Entity\AbstractUuidEntity;
use App\Entity\Traits\CreatedAtTrait;
use App\Entity\Traits\UpdatedAtTrait;
use App\Entity\Traits\WorkspaceTrait;
use App\Integration\Exception\CircularReferenceException;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use GuzzleHttp\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 *
 * @ApiFilter(SearchFilter::class, properties={"workspace"="exact"})
 */
#[ORM\Table]
#[ORM\UniqueConstraint(name: 'uniq_integration_key', columns: ['workspace_id', 'title', 'integration'])]
#[ORM\Entity(repositoryClass: \App\Repository\Core\AssetRepository::class)]
class WorkspaceIntegration extends AbstractUuidEntity implements \Stringable
{
    use CreatedAtTrait;
    use UpdatedAtTrait;
    use WorkspaceTrait;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Groups(['integration:index'])]
    private ?string $title = null;

    #[ORM\Column(type: 'string', length: 100, nullable: false)]
    #[Groups(['integration:index'])]
    private ?string $integration = null;

    #[ORM\ManyToMany(targetEntity: WorkspaceIntegration::class)]
    private ?Collection $needs = null;

    #[ORM\Column(type: 'string', length: 2048, nullable: true)]
    #[Groups(['integration:index'])]
    private ?string $if = null;

    #[ORM\Column(type: 'boolean', nullable: false)]
    #[Groups(['integration:index'])]
    private bool $enabled = true;

    #[ORM\Column(type: 'json', nullable: false)]
    private array $config = [];

    private ?string $optionsJson = null;
    private ?string $optionsYaml = null;

    public function __construct()
    {
        parent::__construct();
        $this->needs = new ArrayCollection();
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): void
    {
        $this->title = $title;
    }

    public function getIntegration(): ?string
    {
        return $this->integration;
    }

    public function setIntegration(?string $integration): void
    {
        $this->integration = $integration;
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    public function setConfig(array $config): void
    {
        $this->config = $config;
    }

    public function getOptionsJson(): string
    {
        if (null !== $this->optionsJson) {
            return $this->optionsJson;
        }

        return \GuzzleHttp\json_encode($this->config, JSON_PRETTY_PRINT);
    }

    public function setOptionsJson(string $options): void
    {
        $this->optionsJson = $options;
        try {
            $this->config = \GuzzleHttp\json_decode($options, true);
        } catch (InvalidArgumentException) {
        }
    }

    public function getOptionsYaml(): string
    {
        if (null !== $this->optionsYaml) {
            return $this->optionsYaml;
        }

        return Yaml::dump($this->config, 10);
    }

    public function setOptionsYaml(string $options): void
    {
        $this->optionsYaml = $options;
        try {
            $this->config = Yaml::parse($options);
        } catch (ParseException) {
        }
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }

    public function getThis(): self
    {
        return $this;
    }

    /**
     * @return WorkspaceIntegration[]
     */
    public function getNeeds(): Collection
    {
        return $this->needs;
    }

    public function addNeed(WorkspaceIntegration $need): void
    {
        $this->needs->add($need);
    }

    public function removeNeed(WorkspaceIntegration $need): void
    {
        if ($this->needs->contains($need)) {
            $this->needs->removeElement($need);
        }
    }

    #[Assert\Callback]
    public function validateNeeds(ExecutionContextInterface $context, $payload): void
    {
        foreach ($this->needs as $need) {
            if ($need === $this) {
                $context
                    ->buildViolation('Cannot reference itself')
                    ->atPath('needs')
                    ->addViolation();

                return;
            }
        }

        try {
            $this->detectCircularNeed([]);
        } catch (CircularReferenceException) {
            $context
                ->buildViolation('Circular Needs detected')
                ->atPath('needs')
                ->addViolation();
        }
    }

    public function detectCircularNeed(array $branch): void
    {
        foreach ($this->needs as $need) {
            if (in_array($need->getId(), $branch, true)) {
                throw new CircularReferenceException();
            }
            $b = $branch;
            $b[] = $need->getId();
            $need->detectCircularNeed($b);
        }
    }

    public function getIf(): ?string
    {
        return $this->if;
    }

    public function setIf(?string $if): void
    {
        $this->if = $if;
    }

    public function __toString(): string
    {
        return sprintf('%s - %s', $this->workspace->getName(), $this->getIntegration());
    }
}
