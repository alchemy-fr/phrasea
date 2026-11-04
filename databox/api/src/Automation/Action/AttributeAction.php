<?php

declare(strict_types=1);

namespace App\Automation\Action;

use Alchemy\AclBundle\Security\PermissionInterface;
use ApiPlatform\Validator\Exception\ValidationException;
use App\Api\Model\Input\Attribute\AssetAttributeBatchUpdateInput;
use App\Api\Model\Input\Attribute\AttributeActionInput;
use App\Attribute\AttributeValidator;
use App\Attribute\BatchAttributeManager;
use App\Automation\AutomationBatch;
use App\Entity\Core\Attribute;
use App\Entity\Core\AttributeDefinition;
use App\Security\Voter\AssetVoter;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Changes an attribute value of the assets, through the batch attribute update.
 */
#[AsTaggedItem(index: self::TYPE)]
final class AttributeAction extends AbstractAutomationAction
{
    public const string TYPE = 'attribute';

    private const array ACTIONS = [
        BatchAttributeManager::ACTION_SET,
        BatchAttributeManager::ACTION_ADD,
        BatchAttributeManager::ACTION_DELETE,
        BatchAttributeManager::ACTION_REPLACE,
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly BatchAttributeManager $batchAttributeManager,
        private readonly AttributeValidator $attributeValidator,
    ) {
    }

    public function validateConfig(array $config): array
    {
        $definitionId = self::normalizeId(self::getRequiredString($config, 'definitionId'));
        $definition = Uuid::isValid($definitionId) ? $this->em->find(AttributeDefinition::class, $definitionId) : null;
        if (!$definition instanceof AttributeDefinition) {
            self::throwViolations(['definitionId' => 'Attribute definition not found.']);
        }
        if (!$this->canEdit($definition)) {
            self::throwViolations(['definitionId' => 'You are not allowed to edit this attribute.']);
        }

        $action = $config['action'] ?? BatchAttributeManager::ACTION_SET;
        if (!in_array($action, self::ACTIONS, true)) {
            self::throwViolations(['action' => sprintf('The action must be one of: %s.', implode(', ', self::ACTIONS))]);
        }

        $locale = $config['locale'] ?? null;
        if (null !== $locale && (!is_string($locale) || '' === $locale)) {
            self::throwViolations(['locale' => 'Invalid locale.']);
        }

        $normalized = [
            'definitionId' => $definitionId,
            'action' => $action,
            'locale' => $locale,
        ];

        switch ($action) {
            case BatchAttributeManager::ACTION_ADD:
                if (!$definition->isMultiple()) {
                    self::throwViolations(['action' => 'This attribute is not multi-valued: use "set".']);
                }
                // no break
            case BatchAttributeManager::ACTION_SET:
                $normalized['value'] = $config['value'] ?? null;
                if (BatchAttributeManager::ACTION_ADD === $action && null === $normalized['value']) {
                    self::throwViolations(['value' => 'This value should not be blank.']);
                }
                if (BatchAttributeManager::ACTION_SET === $action && $definition->isMultiple() && null !== $normalized['value']) {
                    $normalized['value'] = is_array($normalized['value']) ? array_values($normalized['value']) : [$normalized['value']];
                }

                if (null !== $normalized['value']) {
                    $this->validateValue($definition, $normalized);
                }
                break;
            case BatchAttributeManager::ACTION_DELETE:
                break;
            case BatchAttributeManager::ACTION_REPLACE:
                $from = $config['value'] ?? null;
                if (!is_string($from) || '' === $from) {
                    self::throwViolations(['value' => 'The text to replace should not be blank.']);
                }
                $replaceWith = $config['replaceWith'] ?? '';
                if (!is_string($replaceWith)) {
                    self::throwViolations(['replaceWith' => 'This value should be a string.']);
                }
                $regex = (bool) ($config['regex'] ?? false);
                $flags = $config['flags'] ?? null;
                if (null !== $flags && (!is_string($flags) || !preg_match('#^[a-z]*$#', $flags))) {
                    self::throwViolations(['flags' => 'Invalid regex flags.']);
                }

                $normalized += [
                    'value' => $from,
                    'replaceWith' => $replaceWith,
                    'regex' => $regex,
                    'flags' => $flags ?: null,
                ];
                break;
        }

        return $normalized;
    }

    public function processBatch(AutomationBatch $batch): void
    {
        $definition = $this->em->find(AttributeDefinition::class, $batch->config['definitionId']);
        if (!$definition instanceof AttributeDefinition) {
            foreach ($batch->assets as $asset) {
                $batch->state->markFailed($asset, 'Attribute definition not found');
            }

            return;
        }

        $user = $this->getStrictUser();
        foreach ($batch->groupByWorkspace() as $workspaceId => $assets) {
            if ($workspaceId !== $definition->getWorkspaceId()) {
                foreach ($assets as $asset) {
                    $batch->state->markSkipped($asset, 'The attribute definition belongs to another workspace');
                }
                continue;
            }

            $assetIds = [];
            foreach ($assets as $asset) {
                if (!$this->isGranted(AssetVoter::EDIT_ATTRIBUTES, $asset)) {
                    $batch->state->markSkipped($asset, 'Not allowed to edit the attributes of the asset');
                    continue;
                }
                $assetIds[] = $asset->getId();
            }
            if (empty($assetIds)) {
                continue;
            }

            $input = new AssetAttributeBatchUpdateInput();
            $input->actions = [$this->createActionInput($batch->config)];

            try {
                $this->batchAttributeManager->validate($workspaceId, $assetIds, $input);
                $this->batchAttributeManager->handleBatch($workspaceId, $assetIds, $input, $user, true);
            } catch (\Throwable $e) {
                foreach ($assetIds as $assetId) {
                    $batch->state->markFailed($assetId, self::getErrorMessage($e));
                }
                // A failure inside the transaction closes the entity manager
                if (!$this->em->isOpen()) {
                    throw $e;
                }
                continue;
            }

            foreach ($assetIds as $assetId) {
                $batch->state->markProcessed($assetId);
            }
        }
    }

    private function createActionInput(array $config): AttributeActionInput
    {
        $action = new AttributeActionInput();
        $action->definitionId = $config['definitionId'];
        $action->action = $config['action'];
        $action->locale = $config['locale'] ?? null;
        $action->value = $config['value'] ?? null;
        $action->regex = $config['regex'] ?? null;
        $action->flags = $config['flags'] ?? null;
        $action->replaceWith = $config['replaceWith'] ?? null;
        $action->origin = Attribute::ORIGIN_LABELS[Attribute::ORIGIN_MACHINE];
        $action->originVendor = 'automation';

        return $action;
    }

    private function validateValue(AttributeDefinition $definition, array $config): void
    {
        $inputs = [];
        foreach (is_array($config['value']) && $definition->isMultiple() ? $config['value'] : [$config['value']] as $value) {
            $input = $this->createActionInput(['value' => $value] + $config);
            $input->action = BatchAttributeManager::ACTION_ADD;
            $inputs[] = $input;
        }

        try {
            $this->attributeValidator->validateAttributeInputs((string) $definition->getWorkspaceId(), $inputs, 'value');
        } catch (ValidationException $e) {
            $messages = [];
            foreach ($e->getConstraintViolationList() as $violation) {
                $messages[] = (string) $violation->getMessage();
            }
            self::throwViolations(['value' => implode(' ', $messages) ?: 'Invalid value.']);
        } catch (HttpExceptionInterface $e) {
            self::throwViolations(['definitionId' => $e->getMessage()]);
        }
    }

    private function canEdit(AttributeDefinition $definition): bool
    {
        if (!$definition->isEditable()) {
            return false;
        }

        $policy = $definition->getPolicy();

        return null !== $policy
            && ($policy->isEditable() || $this->isGranted(PermissionInterface::EDIT, $policy));
    }

    private static function getErrorMessage(\Throwable $e): string
    {
        if ($e instanceof ValidationException) {
            $messages = [];
            foreach ($e->getConstraintViolationList() as $violation) {
                $messages[] = (string) $violation->getMessage();
            }

            return implode(' ', $messages) ?: $e->getMessage();
        }

        return $e->getMessage();
    }
}
