import {FieldValues} from 'react-hook-form';
import {AsyncRSelectProps, AsyncRSelectWidget} from '@alchemy/react-form';
import {ExposeEntityName} from './exposeType.ts';
import {createIriFromId} from '@alchemy/api';
import {usePaginatedSelectLoader} from '../../../../hooks/usePaginatedSelectLoader.ts';
import {getExposeProfiles} from './exposeApi.ts';

type Props<TFieldValues extends FieldValues> = {
    integrationId: string;
} & AsyncRSelectProps<TFieldValues, false>;

export default function ExposeProfileSelect<TFieldValues extends FieldValues>({
    integrationId,
    ...rest
}: Props<TFieldValues>) {
    const {loadOptions} = usePaginatedSelectLoader({
        load: props =>
            getExposeProfiles({
                ...props,
                integrationId,
            }),
        map: t => {
            return {
                value: createIriFromId(
                    ExposeEntityName.PublicationProfile,
                    t.id
                ),
                label: t.name,
            };
        },
        filterLabels: true,
    });

    return (
        <AsyncRSelectWidget<TFieldValues, false>
            cacheId={'exposeProfiles'}
            {...rest}
            loadOptions={loadOptions}
        />
    );
}
