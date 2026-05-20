import React from 'react';
import {Control, FieldPath, FieldValues, useController} from 'react-hook-form';
import PrivacyWidget from '../Form/PrivacyWidget.tsx';
import {Privacy} from '../../api/privacy.ts';

type Props<TFieldValues extends FieldValues> = {
    control: Control<TFieldValues>;
    name: FieldPath<TFieldValues>;
    inheritedPrivacy?: Privacy;
    disabled?: boolean;
};

export default function PrivacyField<TFieldValues extends FieldValues>({
    control,
    name,
    disabled,
    inheritedPrivacy,
}: Props<TFieldValues>) {
    const {
        field: {onChange, value},
    } = useController<TFieldValues>({
        control,
        name,
        defaultValue: 0 as any,
    });

    return (
        <PrivacyWidget
            onChange={onChange}
            value={value}
            inheritedPrivacy={inheritedPrivacy}
            disabled={disabled}
        />
    );
}
