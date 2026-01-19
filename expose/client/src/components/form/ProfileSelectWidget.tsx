import {
    AsyncRSelectProps,
    AsyncRSelectWidget,
    SelectOption,
} from '@alchemy/react-form';
import {useCallback} from 'react';
import {PublicationProfile} from '../../types.ts';
import {FieldValues} from 'react-hook-form';
import {getProfiles} from '../../api/profileApi.ts';

type Props<TFieldValues extends FieldValues> = {} & AsyncRSelectProps<
    TFieldValues,
    false
>;

export default function ProfileSelectWidget<TFieldValues extends FieldValues>({
    ...rest
}: Props<TFieldValues>) {
    const load = useCallback(
        async (inputValue: string): Promise<SelectOption[]> => {
            const data = (
                await getProfiles({
                    query: inputValue,
                })
            ).result;

            return data
                .map((t: PublicationProfile) => {
                    return {
                        value: t['@id'],
                        label: t.name,
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
            cacheId={'profiles'}
            {...rest}
            loadOptions={load}
        />
    );
}
