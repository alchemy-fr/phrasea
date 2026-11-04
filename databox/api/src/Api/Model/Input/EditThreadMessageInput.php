<?php

declare(strict_types=1);

namespace App\Api\Model\Input;

use Symfony\Component\Validator\Constraints as Assert;

final class EditThreadMessageInput
{
    /**
     * Unchanged when null. May be blank when the message keeps attachments.
     */
    public ?string $content = null;

    /**
     * Attachments to remove, by the `id` of their JSON content (a file, an
     * annotation…).
     *
     * @var string[]|null
     */
    #[Assert\All([new Assert\Type('string')])]
    public ?array $removeAttachments = null;
}
