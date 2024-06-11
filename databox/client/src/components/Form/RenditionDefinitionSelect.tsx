import {useCallback} from 'react';
import {FieldValues} from 'react-hook-form';
import {
    getRenditionDefinitions,
    renditionDefinitionNS,
} from '../../api/rendition';
import {RenditionDefinition} from '../../types';
import {
    AsyncRSelectWidget,
    SelectOption,
    AsyncRSelectProps,
} from '@alchemy/react-form';

type Props<TFieldValues extends FieldValues> = {
    workspaceId: string;
} & AsyncRSelectProps<TFieldValues, false>;

export default function RenditionDefinitionSelect<
    TFieldValues extends FieldValues,
>({workspaceId, ...rest}: Props<TFieldValues>) {
    const load = useCallback(
        async (inputValue: string): Promise<SelectOption[]> => {
            const data = await getRenditionDefinitions({
                workspaceIds: [workspaceId],
            });

            return data.result
                .map((t: RenditionDefinition) => ({
                    value: `${renditionDefinitionNS}/${t.id}`,
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
            cacheId={'rend-definitions'}
            {...rest}
            loadOptions={load}
        />
    );
}
