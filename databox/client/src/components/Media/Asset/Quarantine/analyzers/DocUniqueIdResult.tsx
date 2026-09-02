import {useTranslation} from 'react-i18next';
import AnalysisData from '../AnalysisData.tsx';
import AnalysisMessages, {MessageResolver} from '../AnalysisMessages.tsx';
import DuplicateAssets from '../DuplicateAssets.tsx';
import {AnalyzerName, outputHasDuplicates} from '../analysisTypes.ts';
import {AnalyzerComponentProps} from './types.ts';

const resolve: MessageResolver = (t, type, payload) => {
    switch (type) {
        case 'duplicate_doc_unique_id':
            return t('quarantine.doc_unique_id.duplicate_doc_unique_id', {
                defaultValue:
                    '{{count}} file(s) share the same document unique ID (checked up to {{limit}}).',
                count: payload.count,
                limit: payload.limit,
            });
        default:
            return type;
    }
};

export default function DocUniqueIdResult({
    fileId,
    output,
}: AnalyzerComponentProps) {
    const {t} = useTranslation();
    const data = output.data ?? {};

    return (
        <>
            <AnalysisMessages messages={output.messages} resolve={resolve} />
            <AnalysisData
                rows={[
                    {
                        label: t('quarantine.doc_unique_id.duid', 'Unique ID'),
                        value: data.duid,
                    },
                    {
                        label: t(
                            'quarantine.doc_unique_id.found_in',
                            'Read from field'
                        ),
                        value: data.found_in,
                    },
                    {
                        label: t('quarantine.doc_unique_id.new', 'Generated'),
                        value: data.new ? t('common.yes', 'Yes') : undefined,
                    },
                ]}
            />
            {outputHasDuplicates(output) ? (
                <DuplicateAssets
                    fileId={fileId}
                    analyzerName={AnalyzerName.DocUniqueId}
                />
            ) : null}
        </>
    );
}
