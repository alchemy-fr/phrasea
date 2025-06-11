import {useCallback} from 'react';
import {FieldValues} from 'react-hook-form';
import {
    AsyncRSelectWidget,
    SelectOption,
    AsyncRSelectProps,
} from '@alchemy/react-form';
import {entityTypeNS, getEntityLists} from '../../api/entityList.ts';
import {EntityList} from '../../types.ts';

type Props<TFieldValues extends FieldValues> = {
    workspaceId: string;
    useIRI?: boolean;
} & AsyncRSelectProps<TFieldValues, false>;

export default function EntityListSelect<TFieldValues extends FieldValues>({
    workspaceId,
    useIRI,
    ...rest
}: Props<TFieldValues>) {
    const load = useCallback(
        async (inputValue: string): Promise<SelectOption[]> => {
            const data = await getEntityLists(workspaceId);

            return data.result
                .map((t: EntityList) => {
                    return {
                        value: useIRI ? `${entityTypeNS}/${t.id}` : t.id,
                        label: t.name,
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
            cacheId={'entity-list'}
            {...rest}
            loadOptions={load}
        />
    );
}
