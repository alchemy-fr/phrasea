/** The sections of the "Browse" tab of the left panel, which the user can reorder */
export type SidebarSectionId =
    | 'pinnedStories'
    | 'savedSearches'
    | 'collections';

export const defaultSidebarSections: SidebarSectionId[] = [
    'pinnedStories',
    'savedSearches',
    'collections',
];

/**
 * The order to display the sections in, from the one saved in the
 * preferences: unknown sections (removed since) are dropped, and the ones
 * missing (added since) keep their default place among the others.
 */
export function resolveSidebarSections(
    saved: readonly string[] | undefined
): SidebarSectionId[] {
    const order = (saved ?? []).filter(
        (id, i, list): id is SidebarSectionId =>
            (defaultSidebarSections as string[]).includes(id) &&
            list.indexOf(id) === i
    );
    defaultSidebarSections.forEach((id, i) => {
        if (order.includes(id)) {
            return;
        }
        // After the section preceding it by default, or first
        const previous = defaultSidebarSections
            .slice(0, i)
            .reverse()
            .find(p => order.includes(p));
        order.splice(previous ? order.indexOf(previous) + 1 : 0, 0, id);
    });

    return order;
}

/** `order` with the section at `from` moved to `to` */
export function moveSidebarSection<T>(
    order: readonly T[],
    from: number,
    to: number
): T[] {
    const next = [...order];
    const [moved] = next.splice(from, 1);
    next.splice(to, 0, moved);

    return next;
}
