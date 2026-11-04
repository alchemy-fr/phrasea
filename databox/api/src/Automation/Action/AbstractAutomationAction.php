<?php

declare(strict_types=1);

namespace App\Automation\Action;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use ApiPlatform\Validator\Exception\ValidationException;
use App\Automation\AutomationActionState;
use App\Automation\AutomationRunContext;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

abstract class AbstractAutomationAction implements AutomationActionInterface
{
    use SecurityAwareTrait;

    public function getSecretFields(): array
    {
        return [];
    }

    public function finish(AutomationActionState $state, array $config, AutomationRunContext $context): void
    {
    }

    /**
     * @param array<string, string> $errors messages indexed by configuration key
     *
     * @throws ValidationException
     */
    protected static function throwViolations(array $errors): never
    {
        $list = new ConstraintViolationList();
        foreach ($errors as $path => $message) {
            $list->add(new ConstraintViolation($message, $message, [], null, $path, null));
        }

        throw new ValidationException($list);
    }

    protected static function getRequiredString(array $config, string $key): string
    {
        $value = $config[$key] ?? null;
        if (!is_string($value) || '' === trim($value)) {
            self::throwViolations([$key => 'This value should not be blank.']);
        }

        return trim($value);
    }

    /**
     * Accepts an ID or an IRI (e.g. "/collections/<id>").
     */
    protected static function normalizeId(string $value): string
    {
        $pos = strrpos($value, '/');

        return false === $pos ? $value : substr($value, $pos + 1);
    }

    /**
     * @return string[]
     */
    protected static function getIdList(array $config, string $key): array
    {
        $value = $config[$key] ?? [];
        if (!is_array($value)) {
            self::throwViolations([$key => 'This value should be a list.']);
        }

        $ids = [];
        foreach ($value as $id) {
            if (!is_string($id) || '' === trim($id)) {
                self::throwViolations([$key => 'This value should be a list of IDs.']);
            }
            $ids[] = self::normalizeId(trim($id));
        }

        return array_values(array_unique($ids));
    }
}
