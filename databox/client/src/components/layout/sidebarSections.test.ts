import {describe, expect, it} from 'vitest';
import {
    defaultSidebarSections,
    resolveSidebarSections,
} from './sidebarSections';

describe('resolveSidebarSections', () => {
    it('defaults to pinned stories, saved searches, then collections', () => {
        expect(resolveSidebarSections(undefined)).toEqual([
            'pinnedStories',
            'savedSearches',
            'collections',
        ]);
        expect(resolveSidebarSections([])).toEqual(defaultSidebarSections);
    });

    it('keeps a saved order', () => {
        expect(
            resolveSidebarSections([
                'collections',
                'pinnedStories',
                'savedSearches',
            ])
        ).toEqual(['collections', 'pinnedStories', 'savedSearches']);
    });

    it('drops unknown and repeated sections', () => {
        expect(
            resolveSidebarSections([
                'collections',
                'gone',
                'collections',
                'savedSearches',
                'pinnedStories',
            ])
        ).toEqual(['collections', 'savedSearches', 'pinnedStories']);
    });

    it('inserts a missing section after the one preceding it by default', () => {
        expect(
            resolveSidebarSections(['collections', 'pinnedStories'])
        ).toEqual(['collections', 'pinnedStories', 'savedSearches']);
        expect(
            resolveSidebarSections(['savedSearches', 'collections'])
        ).toEqual(['pinnedStories', 'savedSearches', 'collections']);
    });
});
