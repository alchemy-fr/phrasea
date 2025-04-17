import React from 'react';
import {Control, FieldPath, FieldValues, useController} from 'react-hook-form';
import PrivacyWidget from '../Form/PrivacyWidget.tsx';

type Props<TFieldValues extends FieldValues> = {
    control: Control<TFieldValues>;
    name: FieldPath<TFieldValues>;
    inheritedPrivacy?: number;
};

export default function PrivacyField<TFieldValues extends FieldValues>({
    control,
    name,
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
        />
    );
}
