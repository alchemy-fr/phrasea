import type {AttributeDefinition, BuiltInAttribute} from '@/types/api';
import {normalizeDefinition, useDefinitionsStore} from './definitionsStore';
import {DEFINITIONS_CACHE_KEY} from './definitionsCacheKey';

export {DEFINITIONS_CACHE_KEY};

/**
 * Keeps the definitions store in the session storage of the tab, so that a
 * reload shows the labels right away. The restored definitions are only a
 * first display: each of them is resolved again in the background.
 *
 * An entry belongs to a user (the visible definitions depend on their
 * rights) and to an interface language (the labels are translated): any
 * other entry is dropped, never served.
 */

/** Bumped when the stored format changes: older entries are dropped */
const VERSION = 1;
const WRITE_DELAY = 1000;

export type DefinitionsCacheScope = {
    /** Effective user id (the impersonated one), or `anonymous` */
    user: string;
    /** Interface language */
    locale: string;
};

type Entry = DefinitionsCacheScope & {
    v: number;
    byId: Record<string, AttributeDefinition>;
    builtIn: BuiltInAttribute[];
};

/** The scope of the entries written: none before the first hydration */
let scope: DefinitionsCacheScope | undefined;
let unsubscribe: (() => void) | undefined;
let timer: ReturnType<typeof setTimeout> | undefined;

function removeEntry(): void {
    try {
        sessionStorage.removeItem(DEFINITIONS_CACHE_KEY);
    } catch {
        // ignore
    }
}

function isEntry(value: unknown): value is Entry {
    const e = value as Entry | null;

    return (
        !!e &&
        typeof e === 'object' &&
        !!e.byId &&
        typeof e.byId === 'object' &&
        !Array.isArray(e.byId) &&
        Array.isArray(e.builtIn)
    );
}

/** The stored entry of this scope; any other one is removed */
function readEntry({user, locale}: DefinitionsCacheScope): Entry | undefined {
    let raw: string | null;
    try {
        raw = sessionStorage.getItem(DEFINITIONS_CACHE_KEY);
    } catch {
        return undefined;
    }
    if (!raw) {
        return undefined;
    }
    let entry: unknown;
    try {
        entry = JSON.parse(raw);
    } catch {
        entry = undefined;
    }
    if (
        isEntry(entry) &&
        entry.v === VERSION &&
        entry.user === user &&
        entry.locale === locale
    ) {
        return entry;
    }
    removeEntry();

    return undefined;
}

function write(): void {
    clearTimeout(timer);
    timer = undefined;
    if (!scope) {
        return;
    }
    const {byId, builtIn} = useDefinitionsStore.getState();
    const entry: Entry = {v: VERSION, ...scope, byId, builtIn};
    try {
        sessionStorage.setItem(DEFINITIONS_CACHE_KEY, JSON.stringify(entry));
    } catch {
        // Quota exceeded or storage unavailable: the cache is only a speedup
    }
}

/** The page may be gone before the delayed write */
function flushOnPageHide(): void {
    if (timer !== undefined) {
        write();
    }
}

function startPersisting(): void {
    if (unsubscribe) {
        return;
    }
    const stopStore = useDefinitionsStore.subscribe((state, prev) => {
        if (state.byId !== prev.byId || state.builtIn !== prev.builtIn) {
            clearTimeout(timer);
            timer = setTimeout(write, WRITE_DELAY);
        }
    });
    window.addEventListener('pagehide', flushOnPageHide);
    unsubscribe = () => {
        stopStore();
        window.removeEventListener('pagehide', flushOnPageHide);
    };
}

/**
 * Restores the definitions stored for this user and language under the
 * current ones, revalidates them, then keeps the store persisted for this
 * scope. Called again with another scope (a change of language without
 * reload), the entry of the previous one is dropped and every known
 * definition is resolved again.
 */
export function hydrateDefinitions(next: DefinitionsCacheScope): Promise<void> {
    const previous = scope;
    if (
        previous &&
        previous.user === next.user &&
        previous.locale === next.locale
    ) {
        return Promise.resolve();
    }
    scope = {user: next.user, locale: next.locale};
    startPersisting();
    const stored = readEntry(next);
    const store = useDefinitionsStore.getState();
    if (previous) {
        return store.revalidateAll();
    }
    if (!stored) {
        return Promise.resolve();
    }

    const restored: Record<string, AttributeDefinition> = {};
    Object.values(stored.byId).forEach(d => {
        if (d && typeof d.id === 'string') {
            restored[d.id] = normalizeDefinition(d);
        }
    });
    useDefinitionsStore.setState(s => ({
        byId: {...restored, ...s.byId},
        builtIn: s.builtIn.length > 0 ? s.builtIn : stored.builtIn,
    }));

    // Not marked as resolved nor loaded: asked for again, the definitions
    // deleted or no longer visible meanwhile get dropped
    return Promise.all([
        store.ensure({ids: Object.keys(restored)}),
        store.loadBuiltIn(),
    ]).then(() => undefined);
}

/** Drops the stored entry and stops persisting (sign-out) */
export function clearDefinitionsCache(): void {
    clearTimeout(timer);
    timer = undefined;
    scope = undefined;
    removeEntry();
}

/** Stops persisting and forgets the scope, the entry being kept (tests) */
export function resetDefinitionsCache(): void {
    unsubscribe?.();
    unsubscribe = undefined;
    clearTimeout(timer);
    timer = undefined;
    scope = undefined;
}
