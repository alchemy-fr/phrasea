<?php

namespace Alchemy\NotifyBundle\Model;

use Symfony\Component\Validator\Constraints as Assert;

class Notification
{
    #[Assert\NotBlank]
    public ?string $subject = null;
    #[Assert\NotBlank]
    public ?string $content = null;
    public ?string $topic = null;
}
