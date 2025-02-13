import {useCallback} from 'react';
import {IntegrationType} from '../../types';
import {FieldValues} from 'react-hook-form';
import {
    AsyncRSelectProps,
    AsyncRSelectWidget,
    SelectOption,
} from '@alchemy/react-form';
import {getIntegrationTypes} from '../../api/integrations.ts';

type Props<TFieldValues extends FieldValues> = {} & AsyncRSelectProps<
    TFieldValues,
    false
>;

export default function IntegrationTypeSelect<
    TFieldValues extends FieldValues,
>({...rest}: Props<TFieldValues>) {
    const load = useCallback(
        async (inputValue: string): Promise<SelectOption[]> => {
            const data = await getIntegrationTypes();

            return data
                .map((t: IntegrationType) => ({
                    value: t.id,
                    label: t.title,
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
            cacheId={'integration-types'}
            {...rest}
            loadOptions={load}
        />
    );
}
