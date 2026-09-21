import type {ApiFile, Asset} from '@/types/api';
import {AssetStatus} from '@/types/api';
import {BuiltInAttribute} from '@/features/search/searchState';

/**
 * Mirrors the payload `App\Border\FileAnalyzer` stores on `File::$analysis`:
 * one result per analyzer, each holding `[level, type, payload]` messages.
 */
export type AnalysisMessage = [
    level: number,
    type: string,
    payload?: Record<string, unknown>,
];

export type AnalyzerResult = {
    name: string;
    output?: {
        messages?: AnalysisMessage[];
        data?: Record<string, unknown>;
    };
    actions?: string[];
};

export type FileAnalysis = {
    status?: string;
    /** Set when the whole analysis was skipped */
    message?: string;
    results?: AnalyzerResult[];
};

/** Mirrors App\Border\FileAnalyzer\Dto\LogLevelEnum */
export const logLevelLabels: Record<number, string> = {
    0: 'debug',
    1: 'info',
    2: 'warning',
    3: 'error',
    4: 'critical',
};

export function fileAnalysis(file: ApiFile | undefined): FileAnalysis {
    return (file?.analysis ?? {}) as FileAnalysis;
}

export function analyzerResults(analysis: FileAnalysis): AnalyzerResult[] {
    return Array.isArray(analysis.results) ? analysis.results : [];
}

/** Highest level reported by an analyzer, `-1` when it reported nothing. */
export function resultLevel(result: AnalyzerResult): number {
    return (result.output?.messages ?? []).reduce(
        (max, [level]) => Math.max(max, level),
        -1
    );
}

export function hasAnalysisReport(asset: Asset): boolean {
    const analysis = fileAnalysis(asset.source);

    return !!analysis.message || analyzerResults(analysis).length > 0;
}

/**
 * The condition shared by the navigation tree, the quarantine screen and the
 * "open in search" shortcut: quarantined assets are only returned by the API
 * when the search explicitly filters on their status.
 */
export const quarantineCondition = {
    id: BuiltInAttribute.AssetStatus,
    query: `${BuiltInAttribute.AssetStatus} = ${AssetStatus.Quarantined}`,
};
