/**
 * Mirrors the server-side analysis payload produced by
 * `App\Border\FileAnalyzer` and the individual analyzers under
 * `App\Border\FileAnalyzer\Analyzer`.
 *
 * Each analyzer emits an {@link AnalyzerOutput} (messages + data + duplicates).
 * The `FileAnalyzer` wraps every output into an {@link AnalyzerResult} and
 * aggregates them into the {@link FileAnalysis} stored on `File::$analysis`.
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
    duplicates?: string[];
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

/**
 * Collects the unique duplicate file IDs reported across all analyzers of an
 * analysis (checksum, doc_unique_id, ...).
 */
export function collectDuplicateFileIds(
    analysis: FileAnalysis | null | undefined
): string[] {
    const ids = new Set<string>();
    for (const result of analysis?.results ?? []) {
        for (const id of result.output?.duplicates ?? []) {
            ids.add(id);
        }
    }

    return Array.from(ids);
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
