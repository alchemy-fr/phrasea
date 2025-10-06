import React, {useMemo} from 'react';
import {useTranslation} from 'react-i18next';
import {RSelectProps, RSelectWidget} from '@alchemy/react-form';
import {AssetType} from '../../types.ts';
import {FieldValues} from 'react-hook-form';

type Props<TFieldValues extends FieldValues> = Omit<
    RSelectProps<TFieldValues, false>,
    'options' | 'isMulti'
>;

export default function AssetTypeSelect<TFieldValues extends FieldValues>({
    ...props
}: Props<TFieldValues>) {
    const {t} = useTranslation();

    const options = useMemo(
        () => [
            {
                label: t('asset_type.choice.asset', 'Assets only'),
                value: AssetType.Asset.toString(),
            },
            {
                label: t('asset_type.choice.story', 'Stories only'),
                value: AssetType.Story.toString(),
            },
            {
                label: t('asset_type.choice.both', 'Both'),
                value: AssetType.Both.toString(),
            },
        ],
        [t]
    );

    return (
        // @ts-expect-error TS error control/name
        <RSelectWidget
            {...props}
            options={options}
            normalizeValue={normalizeValue}
            denormalizeValue={denormalizeValue}
        />
    );
}

function normalizeValue(
    value: string | number | null | undefined
): string | undefined {
    return value?.toString();
}

export function denormalizeValue(
    value: AssetType | number | string | undefined | null
): AssetType | null | undefined {
    if (value) {
        if (typeof value === 'string') {
            return parseInt(value) as AssetType;
        }

        return value as AssetType;
    }

    return undefined;
}
