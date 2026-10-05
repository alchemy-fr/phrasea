<?php

declare(strict_types=1);

namespace App\Command;

use App\Integration\Auth\IntegrationTokenManager;
use App\Integration\Auth\IntegrationTokenRenewerInterface;
use App\Integration\IntegrationManager;
use App\Repository\Integration\IntegrationTokenRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpClientExceptionInterface;

/**
 * Meant to run periodically (hourly cron): refreshes the integration tokens
 * whose refresh token expires soon, so that background jobs (e.g. Expose basket
 * synchronization) keep a usable token even when the user is not active.
 */
#[AsCommand('app:integration:renew-tokens', 'Renew the integration tokens about to expire')]
final class RenewIntegrationTokensCommand extends Command
{
    public function __construct(
        private readonly IntegrationTokenRepository $integrationTokenRepository,
        private readonly IntegrationTokenManager $integrationTokenManager,
        private readonly IntegrationManager $integrationManager,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'threshold',
            't',
            InputOption::VALUE_REQUIRED,
            'Renew tokens whose refresh token expires within this number of seconds',
            7200,
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $threshold = (int) $input->getOption('threshold');
        $renewed = $skipped = $failed = 0;

        foreach ($this->integrationTokenRepository->getRenewableTokens($threshold) as $token) {
            $workspaceIntegration = $token->getIntegration();
            $config = $this->integrationManager->getIntegrationConfiguration($workspaceIntegration);
            $integration = $config->getIntegration();
            if (!$integration instanceof IntegrationTokenRenewerInterface) {
                ++$skipped;
                continue;
            }

            try {
                $this->integrationTokenManager->renewToken(
                    $token,
                    fn (string $refreshToken): array => $integration->renewIntegrationToken($config, $refreshToken),
                );
                ++$renewed;
                $output->writeln(sprintf('Renewed token <info>%s</info> (%s, user %s)', $token->getId(), $workspaceIntegration->getName() ?? $workspaceIntegration->getIntegration(), $token->getUserId() ?? '-'));
            } catch (HttpClientExceptionInterface $e) {
                ++$failed;
                // On 400/401 the manager has already removed the token (refresh token revoked).
                $this->logger->warning('Failed to renew integration token', [
                    'tokenId' => $token->getId(),
                    'integrationId' => $workspaceIntegration->getId(),
                    'userId' => $token->getUserId(),
                    'error' => $e->getMessage(),
                ]);
                $output->writeln(sprintf('<error>Failed to renew token %s: %s</error>', $token->getId(), $e->getMessage()));
            }
        }

        $output->writeln(sprintf('%d renewed, %d failed, %d skipped (integration does not support renewal)', $renewed, $failed, $skipped));

        return Command::SUCCESS;
    }
}
