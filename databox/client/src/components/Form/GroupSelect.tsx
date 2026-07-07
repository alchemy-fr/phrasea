import {Group} from '../../types';
import {getGroups} from '../../api/user';
import {FieldValues} from 'react-hook-form';
import React from 'react';
import {isAxiosError} from 'axios';
import {AsyncRSelectProps, AsyncRSelectWidget} from '@alchemy/react-form';
import {NotAllowedSelect} from './NotAllowedSelect.tsx';
import {usePaginatedSelectLoader} from '../../hooks/usePaginatedSelectLoader.ts';

type Props<TFieldValues extends FieldValues, IsMulti extends boolean> = {
    data?: Promise<Group[]> | undefined;
} & AsyncRSelectProps<TFieldValues, IsMulti>;

export default function GroupSelect<
    TFieldValues extends FieldValues,
    IsMulti extends boolean,
>({data, ...props}: Props<TFieldValues, IsMulti>) {
    const [notAllowed, setNotAllowed] = React.useState(false);

    const {loadOptions} = usePaginatedSelectLoader({
        load: async qap => {
            try {
                const groups = await (!qap.query && !qap.nextUrl && data
                    ? data
                    : getGroups(qap));

                return {
                    result: groups,
                    total: groups.length,
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
        map: t => ({
            value: t.id,
            label: t.name,
        }),
        filterLabels: true,
    });

    if (notAllowed) {
        return <NotAllowedSelect {...props} />;
    }

    return (
        <AsyncRSelectWidget
            cacheId={'groups'}
            loadOptions={loadOptions}
            {...props}
        />
    );
}
