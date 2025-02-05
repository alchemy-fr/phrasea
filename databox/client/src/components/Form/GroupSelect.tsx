import {Group} from '../../types';
import {getGroups} from '../../api/user';
import {FieldValues} from 'react-hook-form';
import React from 'react';
import {isAxiosError} from 'axios';
import {NotAllowSelect} from './UserSelect';
import {
    AsyncRSelectWidget,
    SelectOption,
    AsyncRSelectProps,
} from '@alchemy/react-form';

type Props<TFieldValues extends FieldValues> = {
    data?: Promise<Group[]> | undefined;
} & AsyncRSelectProps<TFieldValues, false>;

export default function GroupSelect<TFieldValues extends FieldValues>({
    data,
    ...props
}: Props<TFieldValues>) {
    const [notAllowed, setNotAllowed] = React.useState(false);

    const load = async (
        inputValue?: string | undefined
    ): Promise<SelectOption[]> => {
        try {
            const result = await (!inputValue && data
                ? data
                : getGroups({
                      query: inputValue,
                  }));

            return result
                .map((t: Group) => ({
                    value: t.id,
                    label: t.name,
                }))
                .filter((i: SelectOption) =>
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
        <AsyncRSelectWidget cacheId={'groups'} loadOptions={load} {...props} />
    );
}
