import {User} from '../../types';
import {getUsers} from '../../api/user';
import {FieldValues} from 'react-hook-form';
import {isAxiosError} from 'axios';
import React from 'react';
import {
    AsyncRSelectProps,
    AsyncRSelectWidget,
    SelectOption,
} from '@alchemy/react-form';
import {useEntitiesStore} from '../../store/entitiesStore.ts';

type Props<TFieldValues extends FieldValues, IsMulti extends boolean> = {
    data?: Promise<User[]> | undefined;
} & AsyncRSelectProps<TFieldValues, IsMulti>;

export default function UserSelect<
    TFieldValues extends FieldValues,
    IsMulti extends boolean,
>({data, ...props}: Props<TFieldValues, IsMulti>) {
    const [notAllowed, setNotAllowed] = React.useState(false);
    const store = useEntitiesStore(s => s.store);

    const load = async (
        inputValue?: string | undefined
    ): Promise<SelectOption[]> => {
        try {
            const result = await (!inputValue && data
                ? data
                : getUsers({
                      query: inputValue,
                  }));

            return result
                .map((t: User) => {
                    store(`/users/${t.id}`, t);

                    return {
                        value: t.id,
                        label: t.username,
                    };
                })
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

    return (
        <AsyncRSelectWidget cacheId={'users'} loadOptions={load} {...props} />
    );
}

export function NotAllowSelect<
    TFieldValues extends FieldValues,
    IsMulti extends boolean,
>(props: AsyncRSelectProps<TFieldValues, IsMulti>) {
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
