<?php

declare(strict_types=1);

namespace App\Automation\Action;

use Alchemy\AuthBundle\Repository\GroupRepositoryInterface;
use Alchemy\NotifierBundle\Manager\NotifierManager;
use App\Automation\AutomationActionState;
use App\Automation\AutomationBatch;
use App\Automation\AutomationRunContext;
use App\Service\Asset\Attribute\AssetNameResolver;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Sends a report of the handled assets once the run is over, to users, to the
 * members of groups and to raw email addresses.
 *
 * The report lists the first `maxListed` assets and counts the others. As a
 * failed asset is retried at the next run, it may be reported twice.
 */
#[AsTaggedItem(index: self::TYPE)]
final class EmailReportAction extends AbstractAutomationAction
{
    public const string TYPE = 'email_report';
    public const string TOPIC = 'automation:report';

    public const int MAX_RECIPIENTS = 50;
    public const int DEFAULT_MAX_LISTED = 50;
    public const int MAX_LISTED = 200;
    private const int MAX_SUBJECT_LENGTH = 255;

    public function __construct(
        private readonly NotifierManager $notifierManager,
        private readonly GroupRepositoryInterface $groupRepository,
        private readonly AssetNameResolver $assetNameResolver,
        private readonly ValidatorInterface $validator,
    ) {
    }

    public function validateConfig(array $config): array
    {
        $users = self::getIdList($config, 'users');
        $groups = self::getIdList($config, 'groups');
        $emails = $this->getEmails($config);

        $recipientCount = count($users) + count($groups) + count($emails);
        if (0 === $recipientCount) {
            self::throwViolations(['recipients' => 'Choose at least one recipient.']);
        }
        if ($recipientCount > self::MAX_RECIPIENTS) {
            self::throwViolations(['recipients' => sprintf('There cannot be more than %d recipients (users, groups and emails).', self::MAX_RECIPIENTS)]);
        }

        $sendWhenEmpty = $config['sendWhenEmpty'] ?? false;
        if (!is_bool($sendWhenEmpty)) {
            self::throwViolations(['sendWhenEmpty' => 'This value should be a boolean.']);
        }

        $maxListed = $config['maxListed'] ?? self::DEFAULT_MAX_LISTED;
        if (!is_int($maxListed) || $maxListed < 1 || $maxListed > self::MAX_LISTED) {
            self::throwViolations(['maxListed' => sprintf('This value should be between 1 and %d.', self::MAX_LISTED)]);
        }

        $subject = $config['subject'] ?? null;
        if (null !== $subject && !is_string($subject)) {
            self::throwViolations(['subject' => 'This value should be a string.']);
        }
        $subject = null !== $subject ? trim($subject) : '';
        if (mb_strlen($subject) > self::MAX_SUBJECT_LENGTH) {
            self::throwViolations(['subject' => sprintf('This value is too long. It should have %d characters or less.', self::MAX_SUBJECT_LENGTH)]);
        }

        return [
            'users' => $users,
            'groups' => $groups,
            'emails' => $emails,
            'sendWhenEmpty' => $sendWhenEmpty,
            'maxListed' => $maxListed,
            'subject' => '' !== $subject ? $subject : null,
        ];
    }

    public function processBatch(AutomationBatch $batch): void
    {
        $data = &$batch->state->data;
        $data['total'] ??= 0;
        $data['assets'] ??= [];
        $maxListed = $batch->config['maxListed'] ?? self::DEFAULT_MAX_LISTED;

        foreach ($batch->assets as $asset) {
            ++$data['total'];
            if (count($data['assets']) < $maxListed) {
                $data['assets'][] = [
                    'id' => $asset->getId(),
                    'title' => $this->assetNameResolver->resolveNameAsString($asset),
                    'workspace' => $asset->getWorkspace()?->getName(),
                    'createdAt' => $asset->getCreatedAt()?->format(\DateTimeInterface::ATOM),
                ];
            }

            $batch->state->markProcessed($asset);
        }
    }

    public function finish(AutomationActionState $state, array $config, AutomationRunContext $context): void
    {
        $total = $state->data['total'] ?? 0;
        if (0 === $total && !($config['sendWhenEmpty'] ?? false)) {
            return;
        }

        if (!$this->notifierManager->isEnabled()) {
            throw new \RuntimeException('The notifications are disabled: the report cannot be sent');
        }

        $params = self::buildParams($state->data['assets'] ?? [], $total, $config, $context);

        $userIds = $config['users'] ?? [];
        foreach ($config['groups'] ?? [] as $groupId) {
            $userIds = array_merge($userIds, $this->groupRepository->getGroupMemberIds($groupId));
        }
        $userIds = array_values(array_unique($userIds));

        if (!empty($userIds)) {
            $this->notifierManager->notifyUsers($userIds, self::TOPIC, $params);
        }
        foreach ($config['emails'] ?? [] as $email) {
            $this->notifierManager->notifyEmail($email, self::TOPIC, $params);
        }
    }

    /**
     * The parameters of the notification templates.
     */
    public static function buildParams(array $assets, int $total, array $config, AutomationRunContext $context): array
    {
        return [
            'automationId' => $context->automationId,
            'automationName' => $context->automationName,
            'savedSearchId' => $context->savedSearchId,
            'savedSearchName' => $context->savedSearchName,
            'scope' => $context->scope->value,
            'count' => $total,
            'assets' => $assets,
            'more' => max(0, $total - count($assets)),
            'customSubject' => $config['subject'] ?? null,
            'url' => '/saved-searches/'.$context->savedSearchId,
        ];
    }

    /**
     * @return string[]
     */
    private function getEmails(array $config): array
    {
        $value = $config['emails'] ?? [];
        if (!is_array($value)) {
            self::throwViolations(['emails' => 'This value should be a list.']);
        }

        $emails = [];
        foreach ($value as $email) {
            if (!is_string($email) || '' === trim($email)) {
                self::throwViolations(['emails' => 'This value should be a list of email addresses.']);
            }
            $email = trim($email);
            if (count($this->validator->validate($email, new Email())) > 0) {
                self::throwViolations(['emails' => sprintf('"%s" is not a valid email address.', $email)]);
            }
            $emails[strtolower($email)] ??= $email;
        }

        return array_values($emails);
    }
}
