<?php

declare(strict_types=1);

namespace Alchemy\WebhookBundle\Field;

use Alchemy\WebhookBundle\Entity\Webhook;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;

final class EventsChoiceField
{
    private array $events;

    public function __construct(array $events)
    {
        $this->events = $events;
    }

    public function create(string $propertyName, ?string $label = null)
    {
        $choices = ['All events' => Webhook::ALL_EVENTS];
        foreach ($this->events as $name => $event) {
            $choices[$name] = $name;
        }

        return ChoiceField::new($propertyName, $label)
            ->setChoices($choices)
            ->allowMultipleChoices()
            ->renderAsBadges()
            ->renderExpanded()
//            ->setFormType(EventsChoiceType::class)
            ;
    }
}
