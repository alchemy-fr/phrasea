<?php

declare(strict_types=1);

namespace App\Api\Model\Output;

use App\Api\Model\Output\Traits\CreatedAtDTOTrait;
use App\Api\Model\Output\Traits\UpdatedAtDTOTrait;
use App\Entity\Discussion\Message;
use App\Entity\Discussion\Thread;
use Symfony\Component\Serializer\Annotation\Groups;

class ThreadMessageOutput extends AbstractUuidOutput
{
    use CreatedAtDTOTrait;
    use UpdatedAtDTOTrait;

    #[Groups([Message::GROUP_READ])]
    public ?Thread $thread;

    #[Groups([Message::GROUP_LIST, Message::GROUP_READ])]
    public ?UserOutput $author = null;

    #[Groups([Message::GROUP_LIST, Message::GROUP_READ])]
    public ?string $content = null;

    #[Groups([Message::GROUP_LIST, Message::GROUP_READ])]
    public ?array $attachments = null;
}
