<?php

declare(strict_types=1);

namespace App\File;

enum FileAnalysisUnblockAction: string
{
    // A new asset ingest workflow was dispatched for the asset.
    case IngestDispatched = 'ingest_dispatched';

    // The analyzer job(s) of the latest ingest workflow were re-triggered.
    case JobRerun = 'job_rerun';

    // The failed jobs of the latest ingest workflow were retried (the analyzer
    // never ran because one of its dependencies failed).
    case FailedJobsRetried = 'failed_jobs_retried';

    // Nothing was done (see the reason).
    case Skipped = 'skipped';
}
