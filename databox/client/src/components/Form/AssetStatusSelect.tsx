import React, {useMemo} from 'react';
import {RSelectProps, RSelectWidget} from '@alchemy/react-form';
import {AssetStatus} from '../../types.ts';
import {FieldValues} from 'react-hook-form';
import {getAssetStatusTranslations} from '../../translations/assetStatusTranslations.ts';
import {useTranslation} from 'react-i18next';

type Props<TFieldValues extends FieldValues> = Omit<
    RSelectProps<TFieldValues, false>,
    'options' | 'isMulti'
>;

export default function AssetStatusSelect<TFieldValues extends FieldValues>({
    ...props
}: Props<TFieldValues>) {
    const {t} = useTranslation();
    const options = useMemo(() => {
        const labels = getAssetStatusTranslations(t);

        return [
            AssetStatus.Accepted,
            AssetStatus.Pending,
            AssetStatus.Quarantined,
        ].map(s => ({
            label: labels[s],
            value: s.toString(),
        }));
    }, [t]);

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

function normalizeValue(value: string | number | null): string | null {
    return value?.toString() || null;
}

function denormalizeValue(
    value: AssetStatus | number | string | null
): AssetStatus | null {
    if (value) {
        if (typeof value === 'string') {
            return parseInt(value) as AssetStatus;
        }

        return value as AssetStatus;
    }

    return null;
}
