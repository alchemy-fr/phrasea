import {create} from 'zustand';
import {resolveEntities} from '@/lib/api/assets';

export enum ResolveStatus {
    NotFound = 'not_found',
    NotAllowed = 'not_allowed',
}

export type ResolvedEntity = {id: string; [key: string]: unknown};
export type EntityEntry = ResolvedEntity | ResolveStatus | undefined;

type State = {
    index: Record<string, EntityEntry>;
    pending: Set<string>;
    timer: ReturnType<typeof setTimeout> | null;
    store: (iri: string, entity: ResolvedEntity) => void;
    /** Returns the entity if known, schedules a fetch otherwise */
    request: (iri: string) => EntityEntry;
    flush: () => Promise<void>;
};

/**
 * Batches resolution of entity IRIs referenced by search conditions
 * (`@collection = "id"` → collection name) into a single API call.
 */
export const useEntitiesStore = create<State>((set, get) => ({
    index: {},
    pending: new Set(),
    timer: null,

    store: (iri, entity) => set(s => ({index: {...s.index, [iri]: entity}})),

    request: iri => {
        const {index, pending} = get();
        if (iri in index) {
            return index[iri];
        }
        if (!pending.has(iri)) {
            pending.add(iri);
            if (!get().timer) {
                set({
                    timer: setTimeout(() => {
                        set({timer: null});
                        void get().flush();
                    }, 30),
                });
            }
        }

        return undefined;
    },

    flush: async () => {
        const {pending} = get();
        if (pending.size === 0) {
            return;
        }
        const iris = [...pending];
        pending.clear();
        try {
            const result = await resolveEntities(iris);
            set(s => {
                const index = {...s.index};
                iris.forEach(iri => {
                    const v = result[iri];
                    index[iri] =
                        v === null
                            ? ResolveStatus.NotFound
                            : v === undefined
                              ? ResolveStatus.NotAllowed
                              : (v as ResolvedEntity);
                });

                return {index};
            });
        } catch {
            set(s => {
                const index = {...s.index};
                iris.forEach(iri => {
                    index[iri] = ResolveStatus.NotFound;
                });

                return {index};
            });
        }
    },
}));

export function useEntity(iri: string | undefined): EntityEntry {
    const entry = useEntitiesStore(s => (iri ? s.index[iri] : undefined));
    const request = useEntitiesStore(s => s.request);
    if (iri && entry === undefined) {
        request(iri);
    }

    return entry;
}
