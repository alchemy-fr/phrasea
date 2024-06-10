import {useCallback} from 'react';
import {AttributeClass} from '../../types';
import {FieldValues} from 'react-hook-form';
import {attributeClassNS, getAttributeClasses} from '../../api/attributes';
import {AsyncRSelectWidget, SelectOption, AsyncRSelectProps} from '@alchemy/react-form';

type Props<TFieldValues extends FieldValues> = {
    workspaceId: string;
} & AsyncRSelectProps<TFieldValues, false>;

export default function AttributeClassSelect<TFieldValues extends FieldValues>({
    workspaceId,
    ...rest
}: Props<TFieldValues>) {
    const load = useCallback(
        async (inputValue: string): Promise<SelectOption[]> => {
            const data = (await getAttributeClasses(workspaceId)).result;

            return data
                .map((t: AttributeClass) => ({
                    value: `${attributeClassNS}/${t.id}`,
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
