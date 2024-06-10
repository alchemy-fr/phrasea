import {User} from '../../types';
import {getUsers} from '../../api/user';
import {FieldValues} from 'react-hook-form';
import {isAxiosError} from 'axios';
import React from 'react';
import {AsyncRSelectProps, AsyncRSelectWidget, SelectOption} from '@alchemy/react-form';

type Props<TFieldValues extends FieldValues> = {
    data?: Promise<User[]> | undefined;
} & AsyncRSelectProps<TFieldValues, false>;

export default function UserSelect<TFieldValues extends FieldValues>({
    data,
    ...props
}: Props<TFieldValues>) {
    const [notAllowed, setNotAllowed] = React.useState(false);

    const load = async (
        inputValue?: string | undefined
    ): Promise<SelectOption[]> => {
        try {
            const result = await (!inputValue && data ? data : getUsers());

            return result
                .map((t: User) => ({
                    value: t.id,
                    label: t.username,
                }))
                .filter(i =>
                    i.label
                        .toLowerCase()
                        .includes((inputValue || '').toLowerCase())
                );
        } catch (e) {
            if (isAxiosError(e) && e.response?.status === 403) {
                setNotAllowed(true);
            }

            return [];
        }
    };

    if (notAllowed) {
        return <NotAllowSelect {...props} />;
    }

    return <AsyncRSelectWidget cacheId={'users'} loadOptions={load} {...props} />;
}

export function NotAllowSelect<TFieldValues extends FieldValues>(
    props: AsyncRSelectProps<TFieldValues, false>
) {
    return (
        <AsyncRSelectWidget
            {...props}
            placeholder={`${
                props.placeholder ? `${props.placeholder} ` : ''
            }: 🚫 Not allowed`}
            isDisabled={true}
        />
    );
}
