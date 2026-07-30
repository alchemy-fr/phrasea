import {AlertColor} from '@mui/material';
import {
    AnalysisLogLevel,
    AnalyzerOutput,
    getMessageLevel,
} from './analysisTypes.ts';

/**
 * Maps an analyzer log level to a MUI severity used by `Alert`/`Chip`.
 */
export function levelToSeverity(level: AnalysisLogLevel): AlertColor {
    switch (level) {
        case AnalysisLogLevel.Critical:
        case AnalysisLogLevel.Error:
            return 'error';
        case AnalysisLogLevel.Warning:
            return 'warning';
        case AnalysisLogLevel.Info:
            return 'info';
        case AnalysisLogLevel.Debug:
        default:
            return 'info';
    }
}

/**
 * The highest severity present in an analyzer output (used to color the
 * analyzer card header). Returns `success` when there is nothing to report.
 */
export function getOutputSeverity(output: AnalyzerOutput): AlertColor {
    const highest = (output.messages ?? []).reduce<AnalysisLogLevel>(
        (max, message) => Math.max(max, getMessageLevel(message)),
        AnalysisLogLevel.Debug
    );

    return levelToSeverity(highest);
}

/**
 * Whether an analyzer output caused the file to be rejected
 * (mirrors `AnalysisOutput::isSuccessful()`: error or above).
 */
export function isRejectingOutput(output: AnalyzerOutput): boolean {
    return (output.messages ?? []).some(
        m => getMessageLevel(m) >= AnalysisLogLevel.Error
    );
}
