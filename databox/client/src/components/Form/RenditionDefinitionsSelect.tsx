import {FieldValues} from 'react-hook-form';
import {getRenditionDefinitions} from '../../api/rendition';
import {AsyncRSelectProps, AsyncRSelectWidget} from '@alchemy/react-form';
import {useEntitiesStore} from '../../store/entitiesStore.ts';
import {createIriFromId} from '@alchemy/api';
import {EntityName} from '../../api/types.ts';
import {usePaginatedSelectLoader} from '@alchemy/phrasea-framework';

type Props<TFieldValues extends FieldValues> = {
    workspaceId?: string;
} & AsyncRSelectProps<TFieldValues, true>;

/**
 * Multi-value variant of RenditionDefinitionSelect, holding IRIs.
 */
export default function RenditionDefinitionsSelect<
    TFieldValues extends FieldValues,
>({workspaceId, ...rest}: Props<TFieldValues>) {
    const store = useEntitiesStore(s => s.store);

    const {loadOptions} = usePaginatedSelectLoader({
        load: props =>
            getRenditionDefinitions({
                ...props,
                workspaceIds: workspaceId ? [workspaceId] : undefined,
            }),
        map: t => {
            store(t['@id'], t);

            return {
                value: createIriFromId(EntityName.RenditionDefinition, t.id),
                label: t.displayName,
            };
        },
        filterLabels: true,
    });

    return (
        <AsyncRSelectWidget<TFieldValues, true>
            cacheId={'rend-definitions-multi'}
            {...rest}
            loadOptions={loadOptions}
            isMulti={true as any}
            closeMenuOnSelect={false}
            hideSelectedOptions={false}
        />
    );
}
