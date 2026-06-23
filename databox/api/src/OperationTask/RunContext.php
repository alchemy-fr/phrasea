<?php

namespace App\OperationTask;

use App\Entity\Admin\OperationTask;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Helper\Helper;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;

final class RunContext
{
    private ProgressBar $progressBar;

    public function __construct(
        private OperationTask $taskEntity,
        private EntityManagerInterface $em,
        private BufferedOutput $output,
        private OutputInterface $progressBarOutput,
    ) {
        $this->progressBar = new ProgressBar($this->progressBarOutput);
    }

    public function start(int $total): void
    {
        $this->progressBar->start($total);

        $this->reload();
        $this->taskEntity->setProgress(0);
        $this->taskEntity->setItemTotal($total);
        $this->flush();
    }

    public function advance(int $step = 1): void
    {
        $this->progressBar->advance($step);
        $this->updateState();
    }

    public function finish(): void
    {
        $this->progressBar->finish();
        $this->updateState();
    }

    private function updateState(): void
    {
        $this->reload();
        $this->taskEntity->appendOutput($this->output->fetch());
        $this->taskEntity->setEstimated(Helper::formatTime($this->progressBar->getEstimated(), 2));
        $this->taskEntity->setRemaining(Helper::formatTime($this->progressBar->getRemaining(), 2));
        $this->taskEntity->setProgress($this->progressBar->getProgress());
        $this->flush();
    }

    private function reload(): void
    {
        $this->taskEntity = $this->em->find(OperationTask::class, $this->taskEntity->getId());
        if (!$this->taskEntity) {
            throw new \RuntimeException('Task not found');
        }

        if (OperationTask::STATUS_CANCELLED === $this->taskEntity->getStatus()) {
            throw new \RuntimeException('Task cancelled');
        }
    }

    public function getOutput(): OutputInterface
    {
        return $this->output;
    }

    private function flush(): void
    {
        $this->em->persist($this->taskEntity);
        $this->em->flush();
    }

    public function getProgressBar(): ProgressBar
    {
        return $this->progressBar;
    }
}
