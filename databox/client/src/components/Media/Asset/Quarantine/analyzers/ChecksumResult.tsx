import {useTranslation} from 'react-i18next';
import AnalysisData from '../AnalysisData.tsx';
import AnalysisMessages, {MessageResolver} from '../AnalysisMessages.tsx';
import DuplicateList from '../DuplicateList.tsx';
import {AnalyzerComponentProps} from './types.ts';

const resolve: MessageResolver = (t, type, payload) => {
    switch (type) {
        case 'duplicate_checksum':
            return t('quarantine.checksum.duplicate_checksum', {
                defaultValue:
                    '{{count}} file with an identical checksum already exist (checked up to {{limit}}).',
                defaultValue_other:
                    '{{count}} files with an identical checksum already exist',
                count: payload.count,
                limit: payload.limit,
            });
        default:
            return type;
    }
};

export default function ChecksumResult({output}: AnalyzerComponentProps) {
    const {t} = useTranslation();
    const data = output.data ?? {};

    return (
        <>
            <AnalysisMessages messages={output.messages} resolve={resolve} />
            <AnalysisData
                rows={[
                    {
                        label: t('quarantine.checksum.algorithm', 'Algorithm'),
                        value: data.algorithm,
                    },
                    {
                        label: t('quarantine.checksum.checksum', 'Checksum'),
                        value: data.checksum,
                    },
                    {
                        label: t(
                            'quarantine.checksum.stripped',
                            'Metadata stripped'
                        ),
                        value:
                            data.stripped === undefined
                                ? undefined
                                : data.stripped
                                  ? t('common.yes', 'Yes')
                                  : t('common.no', 'No'),
                    },
                ]}
            />
            <DuplicateList duplicates={output.duplicates} />
        </>
    );
}
