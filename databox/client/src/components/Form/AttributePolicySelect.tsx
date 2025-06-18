import {useCallback} from 'react';
import {AttributePolicy} from '../../types';
import {FieldValues} from 'react-hook-form';
import {attributePolicyNS, getAttributePolicies} from '../../api/attributes';
import {
    AsyncRSelectWidget,
    SelectOption,
    AsyncRSelectProps,
} from '@alchemy/react-form';

type Props<TFieldValues extends FieldValues> = {
    workspaceId: string;
} & AsyncRSelectProps<TFieldValues, false>;

export default function AttributePolicySelect<
    TFieldValues extends FieldValues,
>({workspaceId, ...rest}: Props<TFieldValues>) {
    const load = useCallback(
        async (inputValue: string): Promise<SelectOption[]> => {
            const data = (await getAttributePolicies(workspaceId)).result;

            return data
                .map((t: AttributePolicy) => ({
                    value: `${attributePolicyNS}/${t.id}`,
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
            cacheId={'attr-classes'}
            {...rest}
            loadOptions={load}
        />
    );
}
