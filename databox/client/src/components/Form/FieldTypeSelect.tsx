import {useCallback} from 'react';
import {FieldValues} from 'react-hook-form';
import {typesIcons} from '../../lib/icons';
import {getAttributeFieldTypes} from '../../api/attributes';
import {
    AsyncRSelectProps,
    AsyncRSelectWidget,
    SelectOption,
} from '@alchemy/react-form';
import {AttributeType} from '../../api/types.ts';

type Props<TFieldValues extends FieldValues> = {} & AsyncRSelectProps<
    TFieldValues,
    false
>;

export default function FieldTypeSelect<TFieldValues extends FieldValues>({
    ...rest
}: Props<TFieldValues>) {
    const load = useCallback(
        async (inputValue: string): Promise<SelectOption[]> => {
            const data = await getAttributeFieldTypes();

            return data.result
                .filter(i =>
                    i.displayName
                        .toLowerCase()
                        .includes((inputValue || '').toLowerCase())
                )
                .map(d => ({
                    label: d.displayName,
                    value: d.name,
                    image:
                        typesIcons[d.name as AttributeType] ??
                        typesIcons[AttributeType.Text],
                }));
        },
        []
    );

    return (
        <AsyncRSelectWidget<TFieldValues>
            cacheId={'type'}
            {...rest}
            loadOptions={load}
        />
    );
}
