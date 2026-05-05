import {useCallback} from 'react';
import {FieldValues} from 'react-hook-form';
import {getRenditionPolicies} from '../../api/rendition';
import {RenditionPolicy} from '../../types';
import {
    AsyncRSelectProps,
    AsyncRSelectWidget,
    SelectOption,
} from '@alchemy/react-form';
import {EntityName} from '../../api/types.ts';
import {createIriFromId} from '@alchemy/api';

type Props<TFieldValues extends FieldValues> = {
    workspaceId: string;
} & AsyncRSelectProps<TFieldValues, false>;

export default function RenditionPolicySelect<
    TFieldValues extends FieldValues,
>({workspaceId, ...rest}: Props<TFieldValues>) {
    const load = useCallback(
        async (inputValue: string): Promise<SelectOption[]> => {
            const data = await getRenditionPolicies({workspaceId});

            return data.result
                .map((t: RenditionPolicy) => ({
                    value: createIriFromId(EntityName.RenditionPolicy, t.id),
                    label: t.name,
                }))
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
            cacheId={'rend-classes'}
            {...rest}
            loadOptions={load}
        />
    );
}
