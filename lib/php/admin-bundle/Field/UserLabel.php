<?php

declare(strict_types=1);

namespace Alchemy\AdminBundle\Field;

use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The display name of a user, resolved only when it is rendered, so that the users shown on a
 * whole page are fetched together.
 */
final class UserLabel extends TranslatableMessage
{
    /**
     * @param \Closure(string): string $nameResolver
     */
    public function __construct(
        private readonly string $userId,
        private readonly \Closure $nameResolver,
    ) {
        parent::__construct($userId);
    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    #[\Override]
    public function getMessage(): string
    {
        return ($this->nameResolver)($this->userId);
    }

    /**
     * EasyAdmin prints the choice labels of the index and detail pages as raw HTML.
     */
    #[\Override]
    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return htmlspecialchars($this->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE);
    }
}
