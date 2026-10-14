/**
 * The items to display, in the order saved in the preferences: unknown items
 * (removed since, or unavailable here) are dropped, and the ones missing
 * (added since) keep their default place among the others.
 */
export function resolveOrder<T extends string>(
    saved: readonly string[] | undefined,
    defaults: readonly T[]
): T[] {
    const order = (saved ?? []).filter(
        (id, i, list): id is T =>
            (defaults as readonly string[]).includes(id) &&
            list.indexOf(id) === i
    );
    defaults.forEach((id, i) => {
        if (order.includes(id)) {
            return;
        }
        // After the item preceding it by default, or first
        const previous = defaults
            .slice(0, i)
            .reverse()
            .find(p => order.includes(p));
        order.splice(previous ? order.indexOf(previous) + 1 : 0, 0, id);
    });

    return order;
}

/**
 * The order to save once the displayed items are reordered: the saved items
 * not displayed here keep their slot, the displayed ones fill theirs in the
 * new order.
 */
export function mergeOrder<T extends string>(
    saved: readonly string[] | undefined,
    reordered: readonly T[]
): string[] {
    const next = [...reordered];
    const merged = (saved ?? []).flatMap((id, i, list) => {
        if (list.indexOf(id) !== i) {
            return [];
        }
        if (!(reordered as readonly string[]).includes(id)) {
            return [id];
        }

        return next.length > 0 ? [next.shift()!] : [];
    });

    return [...merged, ...next];
}

/** `order` with the item at `from` moved to `to` */
export function moveItem<T>(
    order: readonly T[],
    from: number,
    to: number
): T[] {
    const next = [...order];
    const [moved] = next.splice(from, 1);
    next.splice(to, 0, moved);

    return next;
}
