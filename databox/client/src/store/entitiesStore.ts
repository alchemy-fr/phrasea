import {create} from 'zustand';
import {resolveEntities} from '../api/asset.ts';

export type EntityCached = {
    id: string;
    label: string;
};

export enum ResolveStatus {
    Unresolved = 0,
    NotFound = 1,
}

export type EntitiesIndex = Record<string, EntityCached | ResolveStatus>;
export type RequestEntities = (entities: string[]) => void;
export type GetOrRequestEntity = (iri: string) => EntityCached | undefined;

type State = {
    stack: string[];
    index: EntitiesIndex;
    get: (iri: string) => EntityCached | null | undefined;
    store: (iri: string, data: EntityCached) => void;
    requestEntities: RequestEntities;
    fetchUnresolved: () => Promise<void>;
    loading: boolean;
};

export const useEntitiesStore = create<State>((set, getState) => ({
    loading: false,
    index: {},
    stack: [],

    get: (iri: string) => {
        const {index} = getState();
        const entity = index[iri];
        if (entity === ResolveStatus.Unresolved) {
            return undefined;
        } else if (entity === ResolveStatus.NotFound) {
            return null;
        }

        return entity as EntityCached;
    },

    store: (iri: string, data: EntityCached) => {
        set(p => ({index: {...p.index, [iri]: data}}));
    },

    requestEntities: (entities: string[]) => {
        const {stack, index} = getState();

        const newStack = [...stack];
        for (const e of entities) {
            if (index[e] !== undefined || newStack.includes(e)) {
                continue;
            }

            newStack.push(e);
        }

        set({
            stack: newStack,
        });
    },

    fetchUnresolved: async () => {
        const {loading, stack} = getState();
        if (loading || stack.length === 0) {
            return;
        }

        set(p => ({
            loading: true,
            stack: [],
            index: {
                ...p.index,
                ...stack.reduce((acc, iri) => {
                    acc[iri] = ResolveStatus.Unresolved;
                    return acc;
                }, {} as EntitiesIndex),
            },
        }));

        try {
            const result = await resolveEntities(stack);

            set(p => {
                const newIndex = {...p.index};
                stack.forEach(iri => {
                    newIndex[iri] = ResolveStatus.NotFound;
                });
                Object.entries(result.entities).map(([iri, value]) => {
                    if (value) {
                        newIndex[iri] = value as EntityCached;
                    }
                });

                return {
                    index: newIndex,
                    loading: false,
                };
            });
        } catch (e: any) {
            set({loading: false});
        }
    },
}));
