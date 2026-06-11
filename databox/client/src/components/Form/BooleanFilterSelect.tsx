import React, {useMemo} from 'react';
import {useTranslation} from 'react-i18next';
import {RSelectProps, RSelectWidget} from '@alchemy/react-form';
import {FieldValues} from 'react-hook-form';
import {isNotNull} from '@alchemy/core';

type Props<TFieldValues extends FieldValues> = Omit<
    RSelectProps<TFieldValues, false>,
    'options' | 'isMulti'
>;

enum Bool {
    Yes = 'y',
    No = 'n',
}

export default function BooleanFilterSelect<TFieldValues extends FieldValues>({
    ...props
}: Props<TFieldValues>) {
    const {t} = useTranslation();

    const options = useMemo(
        () => [
            {
                label: t('boolean_filter.choice.true', 'Yes'),
                value: Bool.Yes,
            },
            {
                label: t('boolean_filter.choice.false', 'No'),
                value: Bool.No,
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

function normalizeValue(value: boolean | number | null): string | null {
    if (isNotNull(value)) {
        return value ? Bool.Yes : Bool.No;
    }

    return null;
}

function denormalizeValue(value: string | null): boolean | null {
    if (value === Bool.Yes) {
        return true;
    } else if (value === Bool.No) {
        return false;
    }
    return null;
}
