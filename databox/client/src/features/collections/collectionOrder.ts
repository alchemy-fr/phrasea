import type {Collection} from '@/types/api';
import {compareCodePoints, naturalSortKey} from '@/lib/utils/naturalSortKey';

type Sortable = Pick<Collection, 'id' | 'name' | 'displayName' | 'createdAt'>;

function createdAtTime(c: Sortable): number | undefined {
    const time = c.createdAt ? Date.parse(c.createdAt) : NaN;

    return Number.isNaN(time) ? undefined : time;
}

/**
 * The order of the collections listed by the API: natural order of the
 * displayed name, then creation date, then id.
 */
export function compareCollections(a: Sortable, b: Sortable): number {
    const byName = compareCodePoints(
        naturalSortKey(a.displayName ?? a.name ?? ''),
        naturalSortKey(b.displayName ?? b.name ?? '')
    );
    if (byName !== 0) {
        return byName;
    }

    const ta = createdAtTime(a);
    const tb = createdAtTime(b);
    if (ta !== undefined && tb !== undefined && ta !== tb) {
        return ta < tb ? -1 : 1;
    }

    return a.id === b.id ? 0 : a.id < b.id ? -1 : 1;
}

/**
 * `ids` with `id` moved (or added) before the first collection ordered after
 * it. Ids of unknown collections are left in place.
 */
export function insertSorted(
    ids: string[],
    id: string,
    collections: Record<string, Sortable | undefined>
): string[] {
    const result = ids.filter(i => i !== id);
    const item = collections[id];
    if (!item) {
        return [...result, id];
    }

    const index = result.findIndex(i => {
        const other = collections[i];

        return !!other && compareCollections(other, item) > 0;
    });
    if (index === -1) {
        result.push(id);
    } else {
        result.splice(index, 0, id);
    }

    return result;
}
