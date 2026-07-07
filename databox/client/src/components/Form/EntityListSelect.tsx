import {FieldValues} from 'react-hook-form';
import {AsyncRSelectProps, AsyncRSelectWidget} from '@alchemy/react-form';
import {getEntityLists} from '../../api/entityList.ts';
import {usePaginatedSelectLoader} from '../../hooks/usePaginatedSelectLoader.ts';
import {EntityName} from '../../api/types.ts';
import {createIriFromId} from '@alchemy/api';

type Props<TFieldValues extends FieldValues> = {
    workspaceId: string;
    useIRI?: boolean;
} & AsyncRSelectProps<TFieldValues, false>;

export default function EntityListSelect<TFieldValues extends FieldValues>({
    workspaceId,
    useIRI,
    ...rest
}: Props<TFieldValues>) {
    const {loadOptions} = usePaginatedSelectLoader({
        load: props =>
            getEntityLists({
                ...props,
                workspaceId,
            }),
        map: t => ({
            value: useIRI ? createIriFromId(EntityName.EntityList, t.id) : t.id,
            label: t.name,
        }),
    });

    return (
        <AsyncRSelectWidget<TFieldValues>
            cacheId={'entity-list'}
            {...rest}
            loadOptions={loadOptions}
        />
    );
}
