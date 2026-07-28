import AnalysisData from '../AnalysisData.tsx';
import AnalysisMessages, {MessageResolver} from '../AnalysisMessages.tsx';
import DuplicateList from '../DuplicateList.tsx';
import {AnalyzerComponentProps} from './types.ts';

/**
 * Fallback renderer used for the `debug` analyzer and for any analyzer that
 * doesn't have a dedicated component yet. It shows the raw message types and
 * data without analyzer-specific copy.
 */
const resolve: MessageResolver = (_t, type, payload) => {
    const keys = Object.keys(payload);
    if (keys.length === 0) {
        return type;
    }

    return `${type} — ${JSON.stringify(payload)}`;
};

export default function GenericResult({output}: AnalyzerComponentProps) {
    const data = output.data ?? {};

    return (
        <>
            <AnalysisMessages messages={output.messages} resolve={resolve} />
            <AnalysisData
                rows={Object.entries(data).map(([key, value]) => ({
                    label: key,
                    value:
                        typeof value === 'object' && value !== null
                            ? JSON.stringify(value)
                            : String(value),
                }))}
            />
            <DuplicateList duplicates={output.duplicates} />
        </>
    );
}
