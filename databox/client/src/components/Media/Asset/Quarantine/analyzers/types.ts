import {ComponentType} from 'react';
import {AnalyzerOutput} from '../analysisTypes.ts';

export type AnalyzerComponentProps = {
    output: AnalyzerOutput;
};

export type AnalyzerComponent = ComponentType<AnalyzerComponentProps>;

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
