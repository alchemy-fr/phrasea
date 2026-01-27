import {
    AsyncRSelectProps,
    AsyncRSelectWidget,
    SelectOption,
} from '@alchemy/react-form';
import {useCallback} from 'react';
import {getPublications} from '../../api/publicationApi.ts';
import {Publication} from '../../types.ts';
import {FieldValues} from 'react-hook-form';

type Props<TFieldValues extends FieldValues> = {} & AsyncRSelectProps<
    TFieldValues,
    false
>;

export default function PublicationSelectWidget<
    TFieldValues extends FieldValues,
>({...rest}: Props<TFieldValues>) {
    const load = useCallback(
        async (inputValue: string): Promise<SelectOption[]> => {
            const data = (
                await getPublications({
                    query: inputValue,
                })
            ).result;

            return data
                .map((t: Publication) => {
                    return {
                        value: t['@id'],
                        label: t.title,
                    };
                })
                .filter(i =>
                    i.label
                        .toLowerCase()
                        .includes((inputValue || '').toLowerCase())
                );
        },
        []
    );

    return (
        <AsyncRSelectWidget<TFieldValues>
            cacheId={'publications'}
            {...rest}
            loadOptions={load}
        />
    );
}
