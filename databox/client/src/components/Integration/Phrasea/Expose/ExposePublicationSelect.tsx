import {FieldValues} from 'react-hook-form';
import apiClient from "../../../../api/api-client.ts";
import {getHydraCollection} from "../../../../api/hydra.ts";
import {AsyncRSelectWidget, AsyncRSelectProps, SelectOption} from '@alchemy/react-form';
import {ExposePublication} from "./exposeType.ts";

type Props<TFieldValues extends FieldValues> = {
    integrationId: string;
} & AsyncRSelectProps<TFieldValues, false>;


export default function ExposePublicationSelect<TFieldValues extends FieldValues>({
    integrationId,
    ...rest
}: Props<TFieldValues>) {
    const load = async (inputValue: string): Promise<SelectOption[]> => {
        const data = getHydraCollection<ExposePublication>((
            await apiClient.get(`/integrations/expose/${integrationId}/proxy/publications`, {
                params: {
                    query: inputValue || '',
                }
            })
        ).data);

        return data.result
            .map((t: ExposePublication) => ({
                value: `/publications/${t.id}`,
                label: t.title,
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
