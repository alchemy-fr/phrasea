import {AttributePolicy} from '../../types';
import {FieldValues} from 'react-hook-form';
import {getAttributePolicies} from '../../api/attributes';
import {AsyncRSelectProps, AsyncRSelectWidget} from '@alchemy/react-form';
import {EntityName} from '../../api/types.ts';
import {createIriFromId} from '@alchemy/api';
import {usePaginatedSelectLoader} from '../../hooks/usePaginatedSelectLoader.ts';

type Props<TFieldValues extends FieldValues> = {
    workspaceId: string;
} & AsyncRSelectProps<TFieldValues, false>;

export default function AttributePolicySelect<
    TFieldValues extends FieldValues,
>({workspaceId, ...rest}: Props<TFieldValues>) {
    const {loadOptions} = usePaginatedSelectLoader({
        load: props =>
            getAttributePolicies({
                ...props,
                workspaceId,
            }),
        map: (t: AttributePolicy) => ({
            value: createIriFromId(EntityName.AttributePolicy, t.id),
            label: t.name,
        }),
        filterLabels: true,
    });

    return (
        <AsyncRSelectWidget
            cacheId={'attr-classes'}
            {...rest}
            loadOptions={loadOptions}
        />
    );
}
