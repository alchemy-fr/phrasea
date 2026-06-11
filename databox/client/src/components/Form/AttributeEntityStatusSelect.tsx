import React, {useMemo} from 'react';
import {useTranslation} from 'react-i18next';
import {RSelectProps, RSelectWidget} from '@alchemy/react-form';
import {AttributeEntityStatus} from '../../types.ts';
import {FieldValues} from 'react-hook-form';

type Props<TFieldValues extends FieldValues> = Omit<
    RSelectProps<TFieldValues, false>,
    'options' | 'isMulti'
>;

export default function AttributeEntityStatusSelect<
    TFieldValues extends FieldValues,
>({...props}: Props<TFieldValues>) {
    const {t} = useTranslation();

    const options = useMemo(
        () => [
            {
                label: t('attribute_entity.status.approved', 'Approved'),
                value: AttributeEntityStatus.Approved.toString(),
            },

            {
                label: t('attribute_entity.status.rejected', 'Rejected'),
                value: AttributeEntityStatus.Rejected.toString(),
            },

            {
                label: t('attribute_entity.status.pending', 'Pending'),
                value: AttributeEntityStatus.Pending.toString(),
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

function denormalizeValue(
    value: AttributeEntityStatus | number | string | undefined | null
): AttributeEntityStatus | null | undefined {
    if (value || value === 0) {
        if (typeof value === 'string') {
            return parseInt(value) as AttributeEntityStatus;
        }

        return value as AttributeEntityStatus;
    }

    return undefined;
}
