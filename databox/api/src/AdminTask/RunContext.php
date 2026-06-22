<?php

namespace App\AdminTask;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\StreamOutput;

final class RunContext
{
    private ProgressBar $progressBar;

    public function __construct(
        private EntityManagerInterface $em,
    ) {
        $output = new StreamOutput(fopen('/dev/null', 'w'));
        $this->progressBar = new ProgressBar($output);
    }

    public function setProgress(float $progress): void
    {
        $this->progressBar->advance($progress);
    }

    public function setItemTotal(int $total): void
    {

    }

    public function setItemCount(int $count): void
    {

    }
}
