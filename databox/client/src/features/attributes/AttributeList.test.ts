import {describe, expect, it} from 'vitest';
import type {Asset, AttributeDefinition, ProfileItem} from '@/types/api';
import {ProfileItemSection, ProfileItemType} from '@/types/api';
import {attributeSectionItems, buildAttributeItems} from './AttributeList';

function definition(id: string): AttributeDefinition {
    return {
        id,
        slug: id,
        name: id,
        searchSlug: `${id}_text_s`,
        workspace: '/workspaces/ws',
        enabled: true,
        multiple: false,
    } as unknown as AttributeDefinition;
}

const title = definition('title');
const description = definition('description');
const keywords = definition('keywords');

const asset = {
    id: 'a1',
    attributes: [title, description, keywords].map(d => ({
        id: `attr-${d.id}`,
        definition: d,
        value: `${d.id} value`,
        multiple: false,
    })),
} as unknown as Asset;

function pinned(
    definitionId: string,
    section = ProfileItemSection.Attributes
): ProfileItem {
    return {
        id: `item-${definitionId}`,
        section,
        type: ProfileItemType.Definition,
        definition: definitionId,
    } as ProfileItem;
}

function build(
    pinnedItems: ProfileItem[],
    options: {pinnedOnly?: boolean; exclusive?: boolean} = {}
) {
    return buildAttributeItems({
        asset,
        pinnedItems,
        pinnedOnly: options.pinnedOnly ?? false,
        exclusive: options.exclusive,
        definitionsIndex: {},
    }).map(i => i.definition?.id);
}

const all = ['title', 'description', 'keywords'];

describe('buildAttributeItems', () => {
    it('lists every attribute in pinned only mode when nothing is pinned', () => {
        expect(build([], {pinnedOnly: true})).toEqual(all);
    });

    it('lists every attribute when nothing is pinned', () => {
        expect(build([])).toEqual(all);
    });

    it('lists every attribute of an exclusive profile pinning nothing', () => {
        expect(build([], {exclusive: true})).toEqual(all);
        expect(build([], {exclusive: true, pinnedOnly: true})).toEqual(all);
    });

    it('lists only the pinned attributes in pinned only mode', () => {
        expect(build([pinned('keywords')], {pinnedOnly: true})).toEqual([
            'keywords',
        ]);
    });

    it('lists only the pinned attributes of an exclusive profile', () => {
        expect(build([pinned('keywords')], {exclusive: true})).toEqual([
            'keywords',
        ]);
    });

    it('lists the pinned attributes first, then the others', () => {
        expect(build([pinned('keywords')])).toEqual([
            'keywords',
            'title',
            'description',
        ]);
    });

    it('ignores the items pinned to other sections', () => {
        const pinnedItems = attributeSectionItems([
            pinned('keywords', ProfileItemSection.Grid),
        ]);

        expect(pinnedItems).toEqual([]);
        expect(build(pinnedItems, {pinnedOnly: true})).toEqual(all);
    });
});
