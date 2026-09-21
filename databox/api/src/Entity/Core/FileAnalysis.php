<?php

declare(strict_types=1);

namespace App\Entity\Core;

use Alchemy\CoreBundle\Entity\AbstractUuidEntity;
use Alchemy\CoreBundle\Entity\Traits\CreatedAtTrait;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Detailed result of the analysis of a File (see App\Border\FileAnalyzer).
 * Kept apart from the File row so that listing files never loads it:
 * the File itself only keeps the analysis date and whether it was accepted.
 */
#[ORM\Entity]
class FileAnalysis extends AbstractUuidEntity
{
    use CreatedAtTrait;

    /**
     * One of the File::ANALYSIS_* statuses.
     */
    #[ORM\Column(type: Types::STRING, length: 20)]
    private string $status;

    /**
     * Fingerprint of the analyzers configuration used, to detect when a file must be re-analyzed.
     */
    #[ORM\Column(type: Types::STRING, length: 32, nullable: true)]
    private ?string $hash = null;

    /**
     * Human readable reason when the analysis was skipped.
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $message = null;

    /**
     * One entry per analyzer: ['name' => string, 'output' => array, 'actions' => ?string[]].
     */
    #[ORM\Column(type: Types::JSON)]
    private array $results = [];

    public function __construct(string $status)
    {
        parent::__construct();
        $this->status = $status;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    public function getHash(): ?string
    {
        return $this->hash;
    }

    public function setHash(?string $hash): void
    {
        $this->hash = $hash;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(?string $message): void
    {
        $this->message = $message;
    }

    public function getResults(): array
    {
        return $this->results;
    }

    public function setResults(array $results): void
    {
        $this->results = array_values($results);
    }

    /**
     * Payload exposed to the API and the workflows.
     */
    public function toArray(): array
    {
        $data = ['status' => $this->status];
        if (null !== $this->message) {
            $data['message'] = $this->message;
        }
        if (!empty($this->results)) {
            $data['results'] = $this->results;
        }
        if (null !== $this->hash) {
            $data['hash'] = $this->hash;
        }

        return $data;
    }
}
