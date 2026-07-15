import {FieldValues} from 'react-hook-form';
import {AsyncRSelectProps, AsyncRSelectWidget} from '@alchemy/react-form';
import {useAttributeDefinitionStore} from '../../store/attributeDefinitionStore.ts';
import {AttributeDefinition, Workspace} from '../../types.ts';
import {EntityName} from '../../api/types.ts';
import {createIriFromId} from '@alchemy/api';
import {usePaginatedSelectLoader} from '@alchemy/phrasea-framework';

type Props<TFieldValues extends FieldValues> = {
    workspaceId: string;
    useIRI?: boolean;
} & AsyncRSelectProps<TFieldValues, false>;

export default function AttributeDefinitionSelect<
    TFieldValues extends FieldValues,
>({workspaceId, useIRI, ...rest}: Props<TFieldValues>) {
    const definitions = useAttributeDefinitionStore(s => s.definitions);
    const loadWorkspace = useAttributeDefinitionStore(s => s.loadWorkspace);

    const {loadOptions} = usePaginatedSelectLoader<AttributeDefinition>({
        load: async () => {
            if (workspaceId) {
                loadWorkspace(workspaceId);
            }

            const result = definitions.filter(
                d => (d.workspace as Workspace)?.id === workspaceId
            );

            return {
                result,
                total: result.length,
            };
        },
        map: t => ({
            value: useIRI
                ? createIriFromId(EntityName.AttributeDefinition, t.id)
                : t.id,
            label: t.displayName,
        }),
        filterLabels: true,
    });

    return (
        <AsyncRSelectWidget<TFieldValues> {...rest} loadOptions={loadOptions} />
    );
}
