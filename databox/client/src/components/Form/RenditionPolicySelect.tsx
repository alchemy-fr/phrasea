import {useCallback} from 'react';
import {FieldValues} from 'react-hook-form';
import {getRenditionPolicies, renditionPolicyNS} from '../../api/rendition';
import {RenditionPolicy} from '../../types';
import {
    AsyncRSelectWidget,
    AsyncRSelectProps,
    SelectOption,
} from '@alchemy/react-form';

type Props<TFieldValues extends FieldValues> = {
    workspaceId: string;
} & AsyncRSelectProps<TFieldValues, false>;

export default function RenditionPolicySelect<
    TFieldValues extends FieldValues,
>({workspaceId, ...rest}: Props<TFieldValues>) {
    const load = useCallback(
        async (inputValue: string): Promise<SelectOption[]> => {
            const data = await getRenditionPolicies(workspaceId);

            return data.result
                .map((t: RenditionPolicy) => ({
                    value: `${renditionPolicyNS}/${t.id}`,
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
