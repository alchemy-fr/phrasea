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

    /**
     * @var string
     */
    private $token;

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

    public function getToken(): ?string
    {
        return $this->token;
    }

    public function setToken(string $token): void
    {
        $this->token = $token;
    }

    public function generateToken(): void
    {
        $this->token = bin2hex(random_bytes(21));
    }

    public function toArray(): array
    {
        $data = [
            'files' => $this->files,
            'form' => $this->formData,
            'user_id' => $this->userId,
        ];

        if ($this->token) {
            $data['token'] = $this->token;
        }

        return $data;
    }

    public static function fromArray(array $data): self
    {
        $instance = new self();
        $instance->setFiles($data['files']);
        $instance->setFormData($data['form'] ?? []);
        $instance->setUserId($data['user_id']);

        return $instance;
    }
}
