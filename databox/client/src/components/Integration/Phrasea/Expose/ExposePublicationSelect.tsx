import {FieldValues} from 'react-hook-form';
import {AsyncRSelectProps, AsyncRSelectWidget} from '@alchemy/react-form';
import {ExposeEntityName} from './exposeType.ts';
import {createIriFromId} from '@alchemy/api';
import {usePaginatedSelectLoader} from '../../../../hooks/usePaginatedSelectLoader.ts';
import {getExposePublications} from './exposeApi.ts';

type Props<TFieldValues extends FieldValues> = {
    integrationId: string;
} & AsyncRSelectProps<TFieldValues, false>;

export default function ExposePublicationSelect<
    TFieldValues extends FieldValues,
>({integrationId, ...rest}: Props<TFieldValues>) {
    const {loadOptions} = usePaginatedSelectLoader({
        load: props =>
            getExposePublications({
                ...props,
                integrationId,
            }),
        map: t => {
            return {
                value: createIriFromId(ExposeEntityName.Publication, t.id),
                label: t.title,
            };
        },
        filterLabels: true,
    });

    return (
        <AsyncRSelectWidget<TFieldValues, false>
            cacheId={'exposePublications'}
            {...rest}
            loadOptions={loadOptions}
        />
    );
}
