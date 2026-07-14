import {FieldValues} from 'react-hook-form';
import {AsyncRSelectProps, AsyncRSelectWidget} from '@alchemy/react-form';
import {getWorkspaceIntegrations} from '../../api/integrations.ts';
import {EntityName} from '../../api/types.ts';
import {createIriFromId} from '@alchemy/api';
import {usePaginatedSelectLoader} from '@alchemy/phrasea-framework';

type Props<TFieldValues extends FieldValues, IsMulti extends boolean> = {
    workspaceId: string;
} & AsyncRSelectProps<TFieldValues, IsMulti>;

export default function WorkspaceIntegrationSelect<
    TFieldValues extends FieldValues,
    IsMulti extends boolean = false,
>({workspaceId, ...rest}: Props<TFieldValues, IsMulti>) {
    const {loadOptions} = usePaginatedSelectLoader({
        load: props =>
            getWorkspaceIntegrations({
                ...props,
                workspaceId,
            }),
        map: t => ({
            value: createIriFromId(EntityName.Integration, t.id),
            label: t.name || t.integrationName,
        }),
    });

    return (
        <AsyncRSelectWidget<TFieldValues, IsMulti>
            cacheId={'wk-integrations'}
            {...rest}
            loadOptions={loadOptions}
        />
    );
}
