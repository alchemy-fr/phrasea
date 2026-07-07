import {User} from '../../types';
import {getUsers} from '../../api/user';
import {FieldValues} from 'react-hook-form';
import {isAxiosError} from 'axios';
import React from 'react';
import {AsyncRSelectProps, AsyncRSelectWidget} from '@alchemy/react-form';
import {useEntitiesStore} from '../../store/entitiesStore.ts';
import {NotAllowedSelect} from './NotAllowedSelect.tsx';
import {usePaginatedSelectLoader} from '../../hooks/usePaginatedSelectLoader.ts';
import {EntityName} from '../../api/types.ts';
import {createIriFromId} from '@alchemy/api';

type Props<TFieldValues extends FieldValues, IsMulti extends boolean> = {
    data?: Promise<User[]> | undefined;
} & AsyncRSelectProps<TFieldValues, IsMulti>;

export default function UserSelect<
    TFieldValues extends FieldValues,
    IsMulti extends boolean,
>({data, ...props}: Props<TFieldValues, IsMulti>) {
    const [notAllowed, setNotAllowed] = React.useState(false);
    const store = useEntitiesStore(s => s.store);

    const {loadOptions} = usePaginatedSelectLoader({
        load: async qap => {
            try {
                const users = await (!qap.query && !qap.nextUrl && data
                    ? data
                    : getUsers(qap));

                return {
                    result: users,
                    total: users.length,
                };
            } catch (e) {
                if (isAxiosError(e) && e.response?.status === 403) {
                    setNotAllowed(true);
                }

                return {
                    result: [],
                    total: 0,
                };
            }
        },
        map: t => {
            store(createIriFromId(EntityName.User, t.id), t);

            return {
                value: t.id,
                label: t.username,
            };
        },
        filterLabels: true,
    });

    if (notAllowed) {
        return <NotAllowedSelect {...props} />;
    }

    return (
        <AsyncRSelectWidget
            cacheId={'users'}
            loadOptions={loadOptions}
            {...props}
        />
    );
}
