import {create} from 'zustand';

export const quarantineQueueKey = ['quarantine-queue'];

type State = {
    /** Ids resolved in this session, hidden from the queue */
    resolved: string[];
    markResolved: (id: string) => void;
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
    markResolved: id =>
        set(s =>
            s.resolved.includes(id) ? s : {resolved: [...s.resolved, id]}
        ),
    reset: () => set({resolved: []}),
}));
