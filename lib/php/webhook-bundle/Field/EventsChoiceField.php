<?php

declare(strict_types=1);

namespace Alchemy\WebhookBundle\Field;

use Alchemy\WebhookBundle\Entity\Webhook;
use Alchemy\WebhookBundle\Form\EventsChoiceType;
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
            $label = sprintf("<b>%s</b>&nbsp;&nbsp;&nbsp;<i>%s</i>", htmlentities($name), $event['description']);
            $choices[$label] = $name;
        }

        return ChoiceField::new($propertyName, $label)
            ->setChoices($choices)
            ->escapeHtml(false)->setFormTypeOption('label_html', true)
            ->allowMultipleChoices()
            ->renderExpanded()
            ->setFormType(EventsChoiceType::class)
            ;
    }
}
