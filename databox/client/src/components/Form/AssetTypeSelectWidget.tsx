import React, {ReactNode, useMemo} from 'react';
import {useTranslation} from 'react-i18next';
import {RSelectWidget} from '@alchemy/react-form';
import {AssetType} from '../../types.ts';

type Props = {
    onChange: (newValue: number | null | undefined) => void;
    value?: number | null | undefined;
    disabled?: boolean;
    label?: ReactNode;
};

export default function AssetTypeSelectWidget({
    onChange,
    value,
    disabled,
    label,
}: Props) {
    const {t} = useTranslation();

    const normalizedValue = normalizeValue(value);

    const options = useMemo(
        () => [
            {
                label: t('asset_type.choice.asset', 'Assets only'),
                value: AssetType.Asset.toString(),
            },
            {
                label: t('asset_type.choice.story', 'Stories only'),
                value: AssetType.Asset.toString(),
            },
            {
                label: t('asset_type.choice.both', 'Both'),
                value: AssetType.Both.toString(),
            },
        ],
        [t]
    );

    return (
        <RSelectWidget
            disabled={disabled}
            label={label}
            onChange={newValue => {
                onChange!(
                    denormalizeValue(
                        newValue?.value as AssetType | null | undefined
                    )
                );
            }}
            value={normalizedValue as any}
            options={options}
        />
    );
}

function normalizeValue(
    value: string | number | null | undefined
): string | undefined {
    return value?.toString();
}

function denormalizeValue(
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
