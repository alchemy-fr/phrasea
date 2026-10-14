import {resolveOrder} from '@/lib/utils/order';

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

/** The order to display the sections in, from the one saved in the preferences */
export function resolveSidebarSections(
    saved: readonly string[] | undefined
): SidebarSectionId[] {
    return resolveOrder(saved, defaultSidebarSections);
}
