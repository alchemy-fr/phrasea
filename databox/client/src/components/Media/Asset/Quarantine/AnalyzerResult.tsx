import {useTranslation} from 'react-i18next';
import {useMemo} from 'react';
import {
    AnalyzerOutput,
    AnalyzerResult as AnalyzerResultType,
} from './analysisTypes.ts';
import {getOutputSeverity} from './severity.ts';
import AnalyzerCard from './AnalyzerCard.tsx';
import ChecksumResult from './analyzers/ChecksumResult.tsx';
import DocUniqueIdResult from './analyzers/DocUniqueIdResult.tsx';
import FilenameResult from './analyzers/FilenameResult.tsx';
import ImageColorspaceResult from './analyzers/ImageColorspaceResult.tsx';
import ImageDimensionResult from './analyzers/ImageDimensionResult.tsx';
import GenericResult from './analyzers/GenericResult.tsx';

type Props = {
    fileId: string;
    result: AnalyzerResultType;
};

/**
 * Renders the body of a single analyzer using its dedicated component.
 * Components are referenced statically (not looked up in a variable) so they
 * stay stable across renders.
 */
function AnalyzerBody({
    fileId,
    name,
    output,
}: {
    fileId: string;
    name: string;
    output: AnalyzerOutput;
}) {
    switch (name) {
        case 'checksum':
            return <ChecksumResult fileId={fileId} output={output} />;
        case 'doc_unique_id':
            return <DocUniqueIdResult fileId={fileId} output={output} />;
        case 'filename':
            return <FilenameResult fileId={fileId} output={output} />;
        case 'image_colorspace':
            return <ImageColorspaceResult fileId={fileId} output={output} />;
        case 'image_dimension':
            return <ImageDimensionResult fileId={fileId} output={output} />;
        default:
            // debug + any analyzer without a dedicated component
            return <GenericResult fileId={fileId} output={output} />;
    }
}

/**
 * Renders a single analyzer's result: a titled, severity-colored card whose
 * body is delegated to the analyzer-specific component.
 */
export default function AnalyzerResult({fileId, result}: Props) {
    const {t} = useTranslation();

    const analyzerLabels: Record<string, string> = useMemo(
        () => ({
            checksum: t('quarantine.analyzer.checksum', 'Checksum'),
            doc_unique_id: t(
                'quarantine.analyzer.doc_unique_id',
                'Document unique ID'
            ),
            filename: t('quarantine.analyzer.filename', 'Filename'),
            image_colorspace: t(
                'quarantine.analyzer.image_colorspace',
                'Image colorspace'
            ),
            image_dimension: t(
                'quarantine.analyzer.image_dimension',
                'Image dimension'
            ),
            debug: t('quarantine.analyzer.debug', 'Debug'),
        }),
        [t]
    );

    return (
        <AnalyzerCard
            title={analyzerLabels[result.name] ?? result.name}
            severity={getOutputSeverity(result.output)}
        >
            <AnalyzerBody
                fileId={fileId}
                name={result.name}
                output={result.output}
            />
        </AnalyzerCard>
    );
}
