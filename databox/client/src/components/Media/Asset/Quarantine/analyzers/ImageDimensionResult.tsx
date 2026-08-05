import {useTranslation} from 'react-i18next';
import AnalysisData from '../AnalysisData.tsx';
import AnalysisMessages, {MessageResolver} from '../AnalysisMessages.tsx';
import {AnalyzerComponentProps} from './types.ts';

const resolve: MessageResolver = (t, type, payload) => {
    switch (type) {
        case 'not_an_image':
            return t('quarantine.image_dimension.not_an_image', {
                defaultValue:
                    'The file is not an image; dimensions were not checked.',
            });
        case 'unreadable_image':
            return t('quarantine.image_dimension.unreadable_image', {
                defaultValue:
                    'The image could not be read to determine its dimensions.',
            });
        case 'min_width':
            return t('quarantine.image_dimension.min_width', {
                defaultValue:
                    'Width {{width}}px is below the minimum of {{min_width}}px.',
                width: payload.width,
                min_width: payload.min_width,
            });
        case 'max_width':
            return t('quarantine.image_dimension.max_width', {
                defaultValue:
                    'Width {{width}}px exceeds the maximum of {{max_width}}px.',
                width: payload.width,
                max_width: payload.max_width,
            });
        case 'min_height':
            return t('quarantine.image_dimension.min_height', {
                defaultValue:
                    'Height {{height}}px is below the minimum of {{min_height}}px.',
                height: payload.height,
                min_height: payload.min_height,
            });
        case 'max_height':
            return t('quarantine.image_dimension.max_height', {
                defaultValue:
                    'Height {{height}}px exceeds the maximum of {{max_height}}px.',
                height: payload.height,
                max_height: payload.max_height,
            });
        default:
            return type;
    }
};

export default function ImageDimensionResult({output}: AnalyzerComponentProps) {
    const {t} = useTranslation();
    const data = output.data ?? {};
    const dimensions =
        data.width !== undefined && data.height !== undefined
            ? `${data.width} × ${data.height} px`
            : undefined;

    return (
        <>
            <AnalysisMessages messages={output.messages} resolve={resolve} />
            <AnalysisData
                rows={[
                    {
                        label: t(
                            'quarantine.image_dimension.dimensions',
                            'Dimensions'
                        ),
                        value: dimensions,
                    },
                ]}
            />
        </>
    );
}
