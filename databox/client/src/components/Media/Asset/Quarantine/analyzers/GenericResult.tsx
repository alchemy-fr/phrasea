import AnalysisData from '../AnalysisData.tsx';
import AnalysisMessages, {MessageResolver} from '../AnalysisMessages.tsx';
import DuplicateAssets from '../DuplicateAssets.tsx';
import {outputHasDuplicates} from '../analysisTypes.ts';
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

export default function GenericResult({
    fileId,
    output,
}: AnalyzerComponentProps) {
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
            {outputHasDuplicates(output) ? (
                <DuplicateAssets fileId={fileId} />
            ) : null}
        </>
    );
}
