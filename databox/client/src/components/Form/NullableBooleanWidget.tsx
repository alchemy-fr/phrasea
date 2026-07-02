import React, {ReactNode, useMemo} from 'react';
import {useTranslation} from 'react-i18next';
import {RSelectWidget} from '@alchemy/react-form';
import {NullableBooleanValue} from './types.ts';

type Props = {
    onChange: (newValue: boolean | null | undefined) => void;
    value?: boolean | null | undefined;
    disabled?: boolean;
    label?: ReactNode;
};

export default function NullableBooleanWidget({
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
                label: t('aql.constant.yes', 'Yes'),
                value: NullableBooleanValue.True,
            },
            {
                label: t('aql.constant.no', 'No'),
                value: NullableBooleanValue.False,
            },
            {
                label: t('aql.constant.na', 'N/A'),
                value: NullableBooleanValue.Unset,
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
                        newValue?.value as
                            | NullableBooleanValue
                            | null
                            | undefined
                    )
                );
            }}
            value={normalizedValue as any}
            options={options}
        />
    );
}

function normalizeValue(
    value: NullableBooleanValue | boolean | null | undefined
): NullableBooleanValue | undefined {
    if (value === true || value === NullableBooleanValue.True) {
        return NullableBooleanValue.True;
    } else if (value === false || value === NullableBooleanValue.False) {
        return NullableBooleanValue.False;
    } else if (value === null || value === NullableBooleanValue.Unset) {
        return NullableBooleanValue.Unset;
    }

    return undefined;
}

function denormalizeValue(
    value: NullableBooleanValue | boolean | undefined | null
): boolean | null | undefined {
    if (value === true || value === NullableBooleanValue.True) {
        return true;
    } else if (value === false || value === NullableBooleanValue.False) {
        return false;
    } else if (value === NullableBooleanValue.Unset) {
        return null;
    }

    return undefined;
}
