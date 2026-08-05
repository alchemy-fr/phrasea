import {useTranslation} from 'react-i18next';
import AnalysisData from '../AnalysisData.tsx';
import AnalysisMessages, {MessageResolver} from '../AnalysisMessages.tsx';
import {AnalyzerComponentProps, formatList} from './types.ts';

const resolve: MessageResolver = (t, type, payload) => {
    switch (type) {
        case 'extension_is_disallowed':
            return t('quarantine.filename.extension_is_disallowed', {
                defaultValue: 'The file extension is not allowed.',
            });
        case 'extension_is_not_allowed':
            return t('quarantine.filename.extension_is_not_allowed', {
                defaultValue:
                    'The file extension is not in the allowed list ({{allowed}}).',
                allowed: formatList(payload.allowed),
            });
        case 'mime_type_is_disallowed':
            return t('quarantine.filename.mime_type_is_disallowed', {
                defaultValue: 'The MIME type is not allowed.',
            });
        case 'mime_type_is_not_allowed':
            return t('quarantine.filename.mime_type_is_not_allowed', {
                defaultValue:
                    'The MIME type is not in the allowed list ({{allowed}}).',
                allowed: formatList(payload.allowed),
            });
        case 'pattern_is_disallowed':
            return t('quarantine.filename.pattern_is_disallowed', {
                defaultValue:
                    'The filename matches a disallowed pattern ({{pattern}}).',
                pattern: payload.disallowed_pattern,
            });
        case 'pattern_is_not_allowed':
            return t('quarantine.filename.pattern_is_not_allowed', {
                defaultValue:
                    'The filename does not match any allowed pattern ({{allowed}}).',
                allowed: formatList(payload.allowed),
            });
        default:
            return type;
    }
};

export default function FilenameResult({output}: AnalyzerComponentProps) {
    const {t} = useTranslation();
    const data = output.data ?? {};

    return (
        <>
            <AnalysisMessages messages={output.messages} resolve={resolve} />
            <AnalysisData
                rows={[
                    {
                        label: t('quarantine.filename.extension', 'Extension'),
                        value: data.extension,
                    },
                ]}
            />
        </>
    );
}
