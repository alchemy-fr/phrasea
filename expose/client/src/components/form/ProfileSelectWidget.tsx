import {AsyncRSelectProps, AsyncRSelectWidget} from '@alchemy/react-form';
import {FieldValues} from 'react-hook-form';
import {getProfiles} from '../../api/profileApi.ts';
import {usePaginatedSelectLoader} from '@alchemy/phrasea-framework';

type Props<TFieldValues extends FieldValues> = {} & AsyncRSelectProps<
    TFieldValues,
    false
>;

export default function ProfileSelectWidget<TFieldValues extends FieldValues>({
    ...rest
}: Props<TFieldValues>) {
    const {loadOptions} = usePaginatedSelectLoader({
        load: props =>
            getProfiles({
                ...props,
            }),
        map: t => {
            return {
                value: t['@id'],
                label: t.name,
            };
        },
        filterLabels: true,
    });

    return (
        <AsyncRSelectWidget<TFieldValues>
            cacheId={'profiles'}
            {...rest}
            loadOptions={loadOptions}
        />
    );
}
