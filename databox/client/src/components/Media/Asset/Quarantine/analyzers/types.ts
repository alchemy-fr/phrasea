import {AnalyzerOutput} from '../analysisTypes.ts';

export type AnalyzerComponentProps = {
    fileId: string;
    output: AnalyzerOutput;
};

/**
 * Formats a list payload (e.g. `allowed`, `disallowed`) for display in a
 * translated message.
 */
export function formatList(value: unknown): string {
    if (Array.isArray(value)) {
        return value.join(', ');
    }

    return String(value ?? '');
}
