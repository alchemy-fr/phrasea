import {FieldValues} from 'react-hook-form';
import {
    AsyncRSelectProps,
    AsyncRSelectWidget,
    SelectOption,
} from '@alchemy/react-form';
import {useAttributeDefinitionStore} from '../../store/attributeDefinitionStore.ts';
import {useCallback} from 'react';
import {AttributeDefinition, Workspace} from '../../types.ts';
import {EntityName} from '../../api/types.ts';
import {createIriFromId} from '@alchemy/api';

type Props<TFieldValues extends FieldValues> = {
    workspaceId: string;
    useIRI?: boolean;
} & AsyncRSelectProps<TFieldValues, false>;

export default function AttributeDefinitionSelect<
    TFieldValues extends FieldValues,
>({workspaceId, useIRI, ...rest}: Props<TFieldValues>) {
    const definitions = useAttributeDefinitionStore(s => s.definitions);
    const loadWorkspace = useAttributeDefinitionStore(s => s.loadWorkspace);

    const load = useCallback(
        async (inputValue: string): Promise<SelectOption[]> => {
            if (workspaceId) {
                loadWorkspace(workspaceId);
            }

            return definitions
                .filter(d => (d.workspace as Workspace)?.id === workspaceId)
                .map((t: AttributeDefinition) => {
                    return {
                        value: useIRI
                            ? createIriFromId(
                                  EntityName.AttributeDefinition,
                                  t.id
                              )
                            : t.id,
                        label: t.displayName,
                    };
                })
                .filter(i =>
                    i.label
                        .toLowerCase()
                        .includes((inputValue || '').toLowerCase())
                );
        },
        [workspaceId]
    );

    return <AsyncRSelectWidget<TFieldValues> {...rest} loadOptions={load} />;
}
