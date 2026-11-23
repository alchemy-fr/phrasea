import {create} from 'zustand';

export const quarantineQueueKey = ['quarantine-queue'];

type State = {
    /** Ids resolved in this session, hidden from the queue */
    resolved: string[];
    markResolved: (ids: string | string[]) => void;
    reset: () => void;
};

/**
 * Assets resolved during this session, kept outside of React Query: the search
 * index lags behind the action that took them out of quarantine, so a refetch
 * — on a new visit to the screen, or right after the action — would list them
 * again. They stay hidden until the queue is explicitly refreshed.
 */
export const useQuarantineQueueStore = create<State>(set => ({
    resolved: [],
    markResolved: ids =>
        set(s => {
            const added = (Array.isArray(ids) ? ids : [ids]).filter(
                id => !s.resolved.includes(id)
            );

            return added.length > 0 ? {resolved: [...s.resolved, ...added]} : s;
        }),
    reset: () => set({resolved: []}),
}));
