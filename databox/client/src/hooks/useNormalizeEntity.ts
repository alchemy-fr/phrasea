import {createIriFromId} from '@alchemy/api';
import {useEntitiesStore} from '../store/entitiesStore.ts';
import {AttributeTypeInstance} from '../components/Media/Asset/Attribute/types/types';

export function useNormalizeEntity() {
    const index = useEntitiesStore(s => s.index);

    return <T>(
        type: AttributeTypeInstance<T>,
        value: T | string | undefined
    ) => {
        if (type.entityIri && value && typeof value === 'string') {
            return index[createIriFromId(type.entityIri, value)] as
                | T
                | undefined;
        }

        return value as T | undefined;
    };
}
