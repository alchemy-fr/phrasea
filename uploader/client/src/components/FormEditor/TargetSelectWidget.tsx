import {useCallback} from 'react';
import {Target} from '../../types';
import {FieldValues} from 'react-hook-form';
import {
    AsyncRSelectProps,
    AsyncRSelectWidget,
    SelectOption,
} from '@alchemy/react-form';
import {listTargets} from '../../api/targetApi.ts';

type Props<TFieldValues extends FieldValues> = AsyncRSelectProps<
    TFieldValues,
    false
>;

export default function TargetSelectWidget<TFieldValues extends FieldValues>(
    props: Props<TFieldValues>
) {
    const load = useCallback(
        async (inputValue: string): Promise<SelectOption[]> => {
            const data = (await listTargets()).result;

            return data
                .map((t: Target) => ({
                    value: `/targets/${t.id}`,
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
            cacheId={'target'}
            {...props}
            loadOptions={load}
        />
    );
}
