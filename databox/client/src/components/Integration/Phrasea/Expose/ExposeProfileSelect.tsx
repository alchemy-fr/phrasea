import {FieldValues} from 'react-hook-form';
import {
    AsyncRSelectWidget,
    AsyncRSelectProps,
    SelectOption,
} from '@alchemy/react-form';
import {ExposeProfile} from './exposeType.ts';
import {getHydraCollection} from '@alchemy/api';
import {apiClient} from '../../../../init.ts';

type Props<TFieldValues extends FieldValues> = {
    integrationId: string;
} & AsyncRSelectProps<TFieldValues, false>;

export default function ExposeProfileSelect<TFieldValues extends FieldValues>({
    integrationId,
    ...rest
}: Props<TFieldValues>) {
    const load = async (inputValue: string): Promise<SelectOption[]> => {
        const data = getHydraCollection<ExposeProfile>(
            (
                await apiClient.get(
                    `/integrations/expose/${integrationId}/proxy/profiles`
                )
            ).data
        );

        return data.result
            .map((t: ExposeProfile) => ({
                value: `/publication-profiles/${t.id}`,
                label: t.name,
            }))
            .filter((i: SelectOption) =>
                i.label.toLowerCase().includes((inputValue || '').toLowerCase())
            );
    };

    return (
        <AsyncRSelectWidget<TFieldValues, false>
            cacheId={'exposeProfiles'}
            {...rest}
            loadOptions={load}
        />
    );
}
