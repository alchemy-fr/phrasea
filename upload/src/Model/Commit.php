<?php

declare(strict_types=1);

namespace App\Model;

use ApiPlatform\Core\Annotation\ApiResource;
use App\Controller\CommitAction;

/**
 * @ApiResource(
 *     shortName="commit",
 *     collectionOperations={
 *         "post"={
 *             "path"="/commit",
 *             "controller"=CommitAction::class,
 *         }
 *     },
 *     itemOperations={}
 * )
 */
final class Commit
{
    /**
     * @var array
     */
    private $files = [];

    /**
     * @var array
     */
    private $formData = [];

    /**
     * @var string
     */
    private $userId;

    public function getFiles(): array
    {
        return $this->files;
    }

    public function setFiles(array $files): void
    {
        $this->files = $files;
    }

    public function getFormData(): array
    {
        return $this->formData;
    }

    public function setFormData(array $formData): void
    {
        $this->formData = $formData;
    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function setUserId(string $userId): void
    {
        $this->userId = $userId;
    }

    public function toArray(): array
    {
        return [
            'files' => $this->getFiles(),
            'form' => $this->getFormData(),
            'user_id' => $this->getUserId(),
        ];
    }

    public static function fromArray(array $data): self
    {
        $instance = new self();
        $instance->setFiles($data['files']);
        $instance->setFormData($data['form']);
        $instance->setUserId($data['user_id']);

        return $instance;
    }
}
