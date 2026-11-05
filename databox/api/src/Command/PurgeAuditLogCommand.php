<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Log\AuditLog;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand('app:audit-log:purge', 'Delete the audit log entries older than the retention delay (AUDIT_LOG_RETENTION_DAYS)')]
final class PurgeAuditLogCommand extends Command
{
    private const int DEFAULT_RETENTION_DAYS = 365;
    private const int BATCH_SIZE = 5000;

    public function __construct(
        private readonly EntityManagerInterface $em,
        #[Autowire('%env(AUDIT_LOG_RETENTION_DAYS)%')]
        private readonly string $retentionDays,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('days', null, InputOption::VALUE_REQUIRED, 'Retention delay in days, instead of AUDIT_LOG_RETENTION_DAYS (0 keeps everything)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $days = $input->getOption('days') ?? $this->retentionDays;
        if ('' === $days) {
            $days = self::DEFAULT_RETENTION_DAYS;
        }
        if (!is_numeric($days) || (int) $days < 0) {
            $output->writeln(sprintf('<error>Invalid retention delay "%s"</error>', $days));

            return Command::INVALID;
        }

        $days = (int) $days;
        if (0 === $days) {
            $output->writeln('Audit log retention is unlimited, nothing to purge.');

            return Command::SUCCESS;
        }

        $connection = $this->em->getConnection();
        $table = $this->em->getClassMetadata(AuditLog::class)->getTableName();
        $before = new \DateTimeImmutable(sprintf('-%d days', $days));

        // By batches: a single DELETE of millions of rows would lock the table for long
        $total = 0;
        do {
            $deleted = (int) $connection->executeStatement(
                sprintf('DELETE FROM %1$s WHERE id IN (SELECT id FROM %1$s WHERE date < :before LIMIT :limit)', $table),
                ['before' => $before, 'limit' => self::BATCH_SIZE],
                ['before' => Types::DATETIME_IMMUTABLE, 'limit' => ParameterType::INTEGER],
            );
            $total += $deleted;
        } while ($deleted >= self::BATCH_SIZE);

        $output->writeln(sprintf('%d audit log entries older than %d days deleted.', $total, $days));

        return Command::SUCCESS;
    }
}
