import {UseFormGetValues, UseFormSetValue} from 'react-hook-form';
import React from 'react';
import {useTranslation} from 'react-i18next';
import {RSelectWidget} from '@alchemy/react-form';
import {RenditionDefinition} from '../../types.ts';

type FormData = RenditionDefinition;

type Props = {
    getValues: UseFormGetValues<FormData>;
    setValue: UseFormSetValue<FormData>;
};

enum Values {
    Main = 'main',
    Preview = 'preview',
    Thumbnail = 'thumbnail',
    AnimatedThumbnail = 'animatedThumbnail',
}

export default function UseAsWidget({getValues, setValue}: Props) {
    const {t} = useTranslation();

    function getResolvedValues(): string[] {
        const values = [];
        const formValues = getValues();
        if (formValues.useAsMain) {
            values.push(Values.Main);
        }
        if (formValues.useAsPreview) {
            values.push(Values.Preview);
        }
        if (formValues.useAsThumbnail) {
            values.push(Values.Thumbnail);
        }
        if (formValues.useAsAnimatedThumbnail) {
            values.push(Values.AnimatedThumbnail);
        }

        return values;
    }

    const [values, setValues] = React.useState<string[]>(getResolvedValues());

    return (
        <RSelectWidget
            label={t(
                'form.rendition_definition.useAs.label',
                'Rendition used for display'
            )}
            isMulti={true}
            onChange={newValue => {
                const newValues = newValue.map((v: any) => v.value);

                setValue('useAsMain', newValues.includes(Values.Main));
                setValue('useAsPreview', newValues.includes(Values.Preview));
                setValue(
                    'useAsThumbnail',
                    newValues.includes(Values.Thumbnail)
                );
                setValue(
                    'useAsAnimatedThumbnail',
                    newValues.includes(Values.AnimatedThumbnail)
                );

                setValues(newValues);
            }}
            value={values as any}
            options={[
                {
                    label: t('form.rendition_definition.useAs.main', 'Main'),
                    value: Values.Main,
                },
                {
                    label: t(
                        'form.rendition_definition.useAs.preview',
                        'Preview'
                    ),
                    value: Values.Preview,
                },
                {
                    label: t(
                        'form.rendition_definition.useAs.thumbnail',
                        'Thumbnail'
                    ),
                    value: Values.Thumbnail,
                },
                {
                    label: t(
                        'form.rendition_definition.useAs.animatedThumbnail',
                        'Active thumbnail'
                    ),
                    value: Values.AnimatedThumbnail,
                },
            ]}
        />
    );
}
