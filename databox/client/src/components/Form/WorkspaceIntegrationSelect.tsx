import {useCallback} from 'react';
import {WorkspaceIntegration} from '../../types';
import {FieldValues} from 'react-hook-form';
import {
    AsyncRSelectProps,
    AsyncRSelectWidget,
    SelectOption,
} from '@alchemy/react-form';
import {getWorkspaceIntegrations} from '../../api/integrations.ts';

type Props<TFieldValues extends FieldValues, IsMulti extends boolean> = {
    workspaceId: string;
} & AsyncRSelectProps<TFieldValues, IsMulti>;

export default function WorkspaceIntegrationSelect<
    TFieldValues extends FieldValues,
    IsMulti extends boolean = false,
>({workspaceId, ...rest}: Props<TFieldValues, IsMulti>) {
    const load = useCallback(
        async (inputValue: string): Promise<SelectOption[]> => {
            const data = await getWorkspaceIntegrations(workspaceId);

            return data
                .map((t: WorkspaceIntegration) => ({
                    value: `/integrations/${t.id}`,
                    label: t.title ?? t.integrationTitle,
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
        <AsyncRSelectWidget<TFieldValues, IsMulti>
            cacheId={'wk-integrations'}
            {...rest}
            loadOptions={load}
        />
    );
}
