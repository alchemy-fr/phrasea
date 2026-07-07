import {FieldValues} from 'react-hook-form';
import {getRenditionDefinitions} from '../../api/rendition';
import {AsyncRSelectProps, AsyncRSelectWidget} from '@alchemy/react-form';
import {useEntitiesStore} from '../../store/entitiesStore.ts';
import {createIriFromId} from '@alchemy/api';
import {EntityName} from '../../api/types.ts';
import {usePaginatedSelectLoader} from '../../hooks/usePaginatedSelectLoader.ts';

type Props<TFieldValues extends FieldValues> = {
    workspaceId?: string;
    useIRI?: boolean;
} & AsyncRSelectProps<TFieldValues, false>;

export default function RenditionDefinitionSelect<
    TFieldValues extends FieldValues,
>({workspaceId, useIRI, ...rest}: Props<TFieldValues>) {
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
                value: useIRI
                    ? createIriFromId(EntityName.RenditionDefinition, t.id)
                    : t.id,
                label: t.displayName,
            };
        },
        filterLabels: true,
    });

    return (
        <AsyncRSelectWidget<TFieldValues>
            cacheId={'rend-definitions'}
            {...rest}
            loadOptions={loadOptions}
        />
    );
}
