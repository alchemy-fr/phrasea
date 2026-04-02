import React, {useMemo} from 'react';
import {useTranslation} from 'react-i18next';
import {RSelectProps, RSelectWidget} from '@alchemy/react-form';
import {AssetTypeFilter} from '../../types.ts';
import {FieldValues} from 'react-hook-form';

type Props<TFieldValues extends FieldValues> = Omit<
    RSelectProps<TFieldValues, false>,
    'options' | 'isMulti'
>;

export default function AssetTypeFilterSelect<
    TFieldValues extends FieldValues,
>({...props}: Props<TFieldValues>) {
    const {t} = useTranslation();

    const options = useMemo(
        () => [
            {
                label: t('asset_type.choice.asset', 'Assets'),
                value: AssetTypeFilter.Asset.toString(),
            },
            {
                label: t('asset_type.choice.story', 'Stories'),
                value: AssetTypeFilter.Story.toString(),
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
            denormalizeValue={denormalizeAssetTypeFilterValue}
        />
    );
}

function normalizeValue(
    value: string | number | null | undefined
): string | undefined {
    return value?.toString();
}

export function denormalizeAssetTypeFilterValue(
    value: AssetTypeFilter | number | string | undefined | null
): AssetTypeFilter | null | undefined {
    if (value) {
        if (typeof value === 'string') {
            return parseInt(value) as AssetTypeFilter;
        }

        return value as AssetTypeFilter;
    }

    return undefined;
}
