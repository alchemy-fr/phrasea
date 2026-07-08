import {FieldValues} from 'react-hook-form';
import {typesIcons} from '../../lib/icons';
import {getAttributeFieldTypes} from '../../api/attributes';
import {
    AsyncRSelectProps,
    AsyncRSelectWidget,
    SelectOption,
} from '@alchemy/react-form';
import {AttributeType} from '../../api/types.ts';
import {usePaginatedSelectLoader} from '@alchemy/phrasea-framework';

type Props<TFieldValues extends FieldValues> = AsyncRSelectProps<
    TFieldValues,
    false
>;

export default function FieldTypeSelect<TFieldValues extends FieldValues>({
    ...rest
}: Props<TFieldValues>) {
    const {loadOptions} = usePaginatedSelectLoader({
        load: () => getAttributeFieldTypes(),
        map: d => {
            return {
                label: d.displayName,
                value: d.name,
                image:
                    typesIcons[d.name as AttributeType] ??
                    typesIcons[AttributeType.Text],
            } as SelectOption;
        },
        filterLabels: true,
    });

    return (
        <AsyncRSelectWidget<TFieldValues, false>
            cacheId={'type'}
            {...rest}
            loadOptions={loadOptions}
        />
    );
}
