<?php

declare(strict_types=1);

namespace Alchemy\WebhookBundle\Form\DataTransformer;

use Alchemy\WebhookBundle\Entity\Webhook;
use Symfony\Component\Form\DataTransformerInterface;

class EventsDataTransformer implements DataTransformerInterface
{
    public function transform($value)
    {
        if (null === $value) {
            return [Webhook::ALL_EVENTS => true];
        }

        return array_fill_keys($value, true);
    }

    public function reverseTransform($value)
    {
        if ($value[Webhook::ALL_EVENTS] ?? false) {
            return null;
        }

        return array_keys(array_filter($value));
    }
}
