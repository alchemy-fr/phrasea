import {useCallback} from 'react';
import {FieldValues} from 'react-hook-form';
import {fieldTypesIcons} from '../../lib/icons';
import {getAttributeFieldTypes} from '../../api/attributes';
import {
    AsyncRSelectProps,
    AsyncRSelectWidget,
    SelectOption,
} from '@alchemy/react-form';

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

            return data
                .filter(i =>
                    i.title
                        .toLowerCase()
                        .includes((inputValue || '').toLowerCase())
                )
                .map(d => ({
                    label: d.title,
                    value: d.name,
                    image: fieldTypesIcons[d.name] ?? fieldTypesIcons.text,
                }));
        },
        []
    );

    return (
        <AsyncRSelectWidget<TFieldValues>
            cacheId={'fieldType'}
            {...rest}
            loadOptions={load}
        />
    );
}
