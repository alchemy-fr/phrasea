import {useCallback} from 'react';
import {FieldValues} from 'react-hook-form';
import {getRenditionDefinitions} from '../../api/rendition';
import {RenditionDefinition} from '../../types';
import {
    AsyncRSelectWidget,
    SelectOption,
    AsyncRSelectProps,
} from '@alchemy/react-form';
import {useEntitiesStore} from '../../store/entitiesStore.ts';
import {getIri} from '@alchemy/api';
import {EntityName} from '../../api/types.ts';

type Props<TFieldValues extends FieldValues> = {
    workspaceId: string;
    useIRI?: boolean;
} & AsyncRSelectProps<TFieldValues, false>;

export default function RenditionDefinitionSelect<
    TFieldValues extends FieldValues,
>({workspaceId, useIRI, ...rest}: Props<TFieldValues>) {
    const store = useEntitiesStore(s => s.store);

    const load = useCallback(
        async (inputValue: string): Promise<SelectOption[]> => {
            const data = await getRenditionDefinitions({
                workspaceIds: [workspaceId],
            });

            return data.result
                .map((t: RenditionDefinition) => {
                    store(t['@id'], t);

                    return {
                        value: useIRI
                            ? getIri(EntityName.RenditionDefinition, t.id)
                            : t.id,
                        label: t.nameTranslated,
                    };
                })
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
            cacheId={'rend-definitions'}
            {...rest}
            loadOptions={load}
        />
    );
}
