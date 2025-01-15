import {UseFormGetValues, UseFormSetValue} from "react-hook-form";
import React from "react";
import {useTranslation} from 'react-i18next';
import {RSelectWidget} from '@alchemy/react-form';
import {RenditionDefinition} from "../../types.ts";


type FormData = RenditionDefinition;

type Props = {
    getValues: UseFormGetValues<FormData>;
    setValue: UseFormSetValue<FormData>;
};

export default function UseAsWidget({
    getValues,
    setValue,
}: Props) {
    const {t} = useTranslation();

    function getResolvedValues(): string[] {
        const values = [];
        const formValues = getValues();
        if (formValues.useAsOriginal) {
            values.push('original');
        }
        if (formValues.useAsPreview) {
            values.push('preview');
        }
        if (formValues.useAsThumbnail) {
            values.push('thumbnail');
        }
        if (formValues.useAsThumbnailActive) {
            values.push('thumbnailActive');
        }

        return values;
    }

    const [values, setValues] = React.useState<string[]>(getResolvedValues());

    return <RSelectWidget
        label={t(
            'form.rendition_definition.useAs.label',
            'This rendition is'
        )}
        isMulti={true}
        onChange={(newValue) => {
            const newValues = newValue.map((v: any) => v.value);

            setValue('useAsOriginal', newValues.includes('original'));
            setValue('useAsPreview', newValues.includes('preview'));
            setValue('useAsThumbnail', newValues.includes('thumbnail'));
            setValue('useAsThumbnailActive', newValues.includes('thumbnailActive'));

            setValues(newValues);
        }}
        value={values as any}
        options={[
            {
                label: t(
                    'form.rendition_definition.useAs.original',
                    'Original'
                ),
                value: 'original',
            },
            {
                label: t(
                    'form.rendition_definition.useAs.preview',
                    'Preview'
                ),
                value: 'preview',
            },
            {
                label: t(
                    'form.rendition_definition.useAs.thumbnail',
                    'Thumbnail'
                ),
                value: 'thumbnail',
            },
            {
                label: t(
                    'form.rendition_definition.useAs.thumbnailActive',
                    'Active thumbnail'
                ),
                value: 'thumbnailActive',
            },
        ]}
    />
}
