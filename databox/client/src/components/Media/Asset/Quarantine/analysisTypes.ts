/**
 * Mirrors the server-side analysis payload produced by
 * `App\Border\FileAnalyzer` and the individual analyzers under
 * `App\Border\FileAnalyzer\Analyzer`.
 *
 * Each analyzer emits an {@link AnalyzerOutput} (messages + data).
 * The `FileAnalyzer` wraps every output into an {@link AnalyzerResult} and
 * aggregates them into the {@link FileAnalysis} stored on `File::$analysis`.
 * The duplicate files themselves live in the `file_duplicate` table and are
 * resolved as assets through `GET /files/{id}/duplicates`.
 */

// Mirrors App\Border\FileAnalyzer\Dto\LogLevelEnum
export enum AnalysisLogLevel {
    Debug = 0,
    Info = 1,
    Warning = 2,
    Error = 3,
    Critical = 4,
}

// Mirrors File::ANALYSIS_* constants
export enum AnalysisStatus {
    Success = 'success',
    Failed = 'failed',
    Skipped = 'skipped',
    Bypassed = 'bypassed',
}

/**
 * Mirrors App\Entity\Core\FileAnalysisStateEnum: the flattened state of the
 * analysis, computed server-side from the `File::$analysis` JSON column.
 *
 * Whether that state blocks the file is a separate axis, carried by
 * `ApiFile.analysisEnforced` (the workspace `fileAnalysisRequired` setting).
 */
export enum FileAnalysisState {
    // No analysis has run yet. Shown as "in progress" when enforced.
    NotAnalyzed = 'not_analyzed',
    // The file never needed an analysis (renditions).
    NotApplicable = 'not_applicable',
    Passed = 'passed',
    Failed = 'failed',
    Skipped = 'skipped',
    Bypassed = 'bypassed',
}

// Mirrors App\Integration\Core\FileAnalyzer\FileAnalyzerAssetActionEnum
export enum FileAnalyzerAssetAction {
    Quarantine = 'quarantine',
    MoveToTrash = 'move_to_trash',
    Delete = 'delete',
}

/**
 * Serialized form of `App\Border\FileAnalyzer\Dto\AnalysisMessage::toArray()`:
 * a tuple of `[level, type, payload]`.
 */
export type AnalysisMessage = [
    AnalysisLogLevel,
    string,
    Record<string, any> | undefined,
];

export type AnalyzerOutput = {
    messages?: AnalysisMessage[];
    data?: Record<string, any>;
};

export type AnalyzerResult = {
    name: string;
    output: AnalyzerOutput;
    actions?: FileAnalyzerAssetAction[];
};

export type FileAnalysis = {
    status?: AnalysisStatus | string;
    // Present when the whole analysis was skipped
    message?: string;
    results?: AnalyzerResult[];
};

const duplicateMessagePrefix = 'duplicate_';

/**
 * Whether an analyzer output reported at least one duplicate
 * (i.e. carries a `duplicate_*` message).
 */
export function outputHasDuplicates(
    output: AnalyzerOutput | null | undefined
): boolean {
    return (output?.messages ?? []).some(m =>
        getMessageType(m).startsWith(duplicateMessagePrefix)
    );
}

/**
 * Whether any analyzer of the analysis reported duplicates.
 */
export function hasAnalysisDuplicates(
    analysis: FileAnalysis | null | undefined
): boolean {
    return (analysis?.results ?? []).some(result =>
        outputHasDuplicates(result.output)
    );
}

// Analyzer names (AnalyzerInterface::getName())
export enum AnalyzerName {
    Checksum = 'checksum',
    DocUniqueId = 'doc_unique_id',
    Filename = 'filename',
    ImageColorspace = 'image_colorspace',
    ImageDimension = 'image_dimension',
    Debug = 'debug',
}

/**
 * Whether a specific analyzer reported at least one duplicate in the analysis.
 */
export function hasAnalyzerDuplicates(
    analysis: FileAnalysis | null | undefined,
    analyzerName: AnalyzerName
): boolean {
    return (analysis?.results ?? []).some(
        result =>
            result.name === analyzerName && outputHasDuplicates(result.output)
    );
}

export function getMessageLevel(message: AnalysisMessage): AnalysisLogLevel {
    return message[0];
}

export function getMessageType(message: AnalysisMessage): string {
    return message[1];
}

export function getMessagePayload(
    message: AnalysisMessage
): Record<string, any> {
    return message[2] ?? {};
}
