import React from 'react';
import {FieldValues} from 'react-hook-form';
import {locales} from '@alchemy/i18n/src/Locale/locales';
import RSelectWidget, {RSelectProps} from '../RSelectWidget';

type Props<TFieldValues extends FieldValues> = RSelectProps<
    TFieldValues,
    false
>;

export default function LocaleSelectWidget<TFieldValues extends FieldValues>(
    props: Props<TFieldValues>
) {
    const options = React.useMemo(
        () =>
            Object.keys(locales).map(k => ({
                value: k,
                label: locales[k],
            })),
        []
    );

    return <RSelectWidget {...props} options={options} />;
}
