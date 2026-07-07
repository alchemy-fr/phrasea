import {FieldValues} from 'react-hook-form';
import React from 'react';
import {AsyncRSelectProps, AsyncRSelectWidget} from '@alchemy/react-form';

export function NotAllowedSelect<
    TFieldValues extends FieldValues,
    IsMulti extends boolean,
>(props: AsyncRSelectProps<TFieldValues, IsMulti>) {
    return (
        <AsyncRSelectWidget<TFieldValues, IsMulti>
            {...props}
            placeholder={`${
                props.placeholder ? `${props.placeholder} ` : ''
            }: 🚫 Not allowed`}
            isDisabled={true}
        />
    );
}
