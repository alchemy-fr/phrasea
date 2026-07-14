import {AsyncRSelectProps, AsyncRSelectWidget} from '@alchemy/react-form';
import {getPublications} from '../../api/publicationApi.ts';
import {FieldValues} from 'react-hook-form';
import {usePaginatedSelectLoader} from '@alchemy/phrasea-framework';

type Props<TFieldValues extends FieldValues> = {} & AsyncRSelectProps<
    TFieldValues,
    false
>;

export default function PublicationSelectWidget<
    TFieldValues extends FieldValues,
>({...rest}: Props<TFieldValues>) {
    const {loadOptions} = usePaginatedSelectLoader({
        load: props =>
            getPublications({
                ...props,
            }),
        map: t => {
            return {
                value: t['@id'],
                label: t.title,
            };
        },
        filterLabels: true,
    });

    return (
        <AsyncRSelectWidget<TFieldValues>
            cacheId={'publications'}
            {...rest}
            loadOptions={loadOptions}
        />
    );
}
