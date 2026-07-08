import {FieldValues} from 'react-hook-form';
import {getRenditionPolicies} from '../../api/rendition';
import {AsyncRSelectProps, AsyncRSelectWidget} from '@alchemy/react-form';
import {EntityName} from '../../api/types.ts';
import {createIriFromId} from '@alchemy/api';
import {usePaginatedSelectLoader} from '@alchemy/phrasea-framework';

type Props<TFieldValues extends FieldValues> = {
    workspaceId: string;
} & AsyncRSelectProps<TFieldValues, false>;

export default function RenditionPolicySelect<
    TFieldValues extends FieldValues,
>({workspaceId, ...rest}: Props<TFieldValues>) {
    const {loadOptions} = usePaginatedSelectLoader({
        load: props =>
            getRenditionPolicies({
                ...props,
                workspaceId,
            }),
        map: t => {
            return {
                value: createIriFromId(EntityName.RenditionPolicy, t.id),
                label: t.name,
            };
        },
        filterLabels: true,
    });

    return (
        <AsyncRSelectWidget<TFieldValues>
            cacheId={'rend-classes'}
            {...rest}
            loadOptions={loadOptions}
        />
    );
}
