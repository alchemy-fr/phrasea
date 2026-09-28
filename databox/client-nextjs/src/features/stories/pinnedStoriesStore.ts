import {create} from 'zustand';

const storageKey = 'dbx.pinned-stories';
export const maxPinnedStories = 50;

/** Toggles `id` in the list; pinning past `max` drops the oldest pins. */
export function togglePinned(
    list: string[],
    id: string,
    max = maxPinnedStories
): string[] {
    if (list.includes(id)) {
        return list.filter(i => i !== id);
    }

    return [...list, id].slice(-max);
}

function read(): string[] {
    try {
        const raw = window.localStorage.getItem(storageKey);
        const parsed: unknown = raw ? JSON.parse(raw) : [];

        return Array.isArray(parsed)
            ? parsed.filter((i): i is string => typeof i === 'string')
            : [];
    } catch {
        return [];
    }
}

function write(ids: string[]): void {
    try {
        window.localStorage.setItem(storageKey, JSON.stringify(ids));
    } catch {
        // storage unavailable
    }
}

type State = {
    /** Pinned story ids, oldest first */
    ids: string[];
    toggle: (id: string) => void;
    unpin: (id: string) => void;
};

/**
 * Stories pinned in the sidebar, local to the browser: a shelf to drop
 * assets on while working on them.
 */
export const usePinnedStoriesStore = create<State>((set, get) => ({
    ids: typeof window === 'undefined' ? [] : read(),
    toggle: id => {
        const ids = togglePinned(get().ids, id);
        write(ids);
        set({ids});
    },
    unpin: id => {
        if (!get().ids.includes(id)) {
            return;
        }
        const ids = get().ids.filter(i => i !== id);
        write(ids);
        set({ids});
    },
}));

export function useIsStoryPinned(id: string): boolean {
    return usePinnedStoriesStore(s => s.ids.includes(id));
}
