import {useTranslation} from 'react-i18next';
import AnalysisData from '../AnalysisData.tsx';
import AnalysisMessages, {MessageResolver} from '../AnalysisMessages.tsx';
import {AnalyzerComponentProps, formatList} from './types.ts';

const resolve: MessageResolver = (t, type, payload) => {
    switch (type) {
        case 'not_an_image':
            return t('quarantine.image_colorspace.not_an_image', {
                defaultValue:
                    'The file is not an image; colorspace was not checked.',
            });
        case 'unknown_colorspace':
            return t('quarantine.image_colorspace.unknown_colorspace', {
                defaultValue:
                    'The colorspace of the image could not be determined.',
            });
        case 'disallowed_colorspace':
            return t('quarantine.image_colorspace.disallowed_colorspace', {
                defaultValue:
                    'The image colorspace is not allowed ({{disallowed}}).',
                disallowed: formatList(payload.disallowed),
            });
        case 'not_in_allowed_colorspaces':
            return t('quarantine.image_colorspace.not_in_allowed_colorspaces', {
                defaultValue:
                    'The image colorspace is not in the allowed list ({{allowed}}).',
                allowed: formatList(payload.allowed),
            });
        default:
            return type;
    }
};

export default function ImageColorspaceResult({
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
                        label: t(
                            'quarantine.image_colorspace.colorspace',
                            'Colorspace'
                        ),
                        value: data.colorspace,
                    },
                ]}
            />
        </>
    );
}
