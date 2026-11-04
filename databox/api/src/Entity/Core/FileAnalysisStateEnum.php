<?php

declare(strict_types=1);

namespace App\Entity\Core;

/**
 * The state of the file analysis, as exposed to API consumers.
 *
 * `File::$analysis` is a single nullable JSON column that conflates several
 * realities: never analyzed (null), analysis not applicable (empty array) and
 * the four persisted `File::ANALYSIS_*` statuses. This enum flattens them into
 * one explicit value so clients do not have to infer it.
 *
 * Whether the state actually blocks the file is a separate, orthogonal concern
 * carried by `Workspace::isFileAnalysisRequired()`.
 */
enum FileAnalysisStateEnum: string
{
    // No analysis has run yet. Presented as "pending" when the workspace
    // requires the analysis.
    case NotAnalyzed = 'not_analyzed';

    // The file does not need to be analyzed at all (e.g. renditions, see
    // File::setNoAnalysisNeeded()).
    case NotApplicable = 'not_applicable';

    case Passed = 'passed';
    case Failed = 'failed';
    case Skipped = 'skipped';
    case Bypassed = 'bypassed';
}
