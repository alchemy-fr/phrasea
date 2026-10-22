import {beforeAll, describe, expect, it, vi} from 'vitest';
import {fireEvent, render, screen} from '@testing-library/react';
import {I18nextProvider} from 'react-i18next';
import {createI18n} from '@/i18n';
import type {AttributeEntity, EntityList} from '@/types/api';
import {AttributeEntityStatus} from '@/types/api';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/menu';
import {type EntityMenuActions, EntityMenuItems} from './EntityMenuItems';
import {DRAFT_PREFIX} from './drafts';

beforeAll(() => {
    globalThis.ResizeObserver ??= class {
        observe() {}
        unobserve() {}
        disconnect() {}
    } as unknown as typeof ResizeObserver;
});

const entityOf = (id: string, more: Partial<AttributeEntity> = {}) =>
    ({
        id,
        value: id,
        status: AttributeEntityStatus.Approved,
        ...more,
    }) as unknown as AttributeEntity;

function renderMenu(
    entities: AttributeEntity[],
    list: Partial<EntityList> = {withImages: true}
) {
    const actions: EntityMenuActions = {
        onEdit: vi.fn(),
        onSetStatus: vi.fn(),
        onPreview: vi.fn(),
        onPickImage: vi.fn(),
        onRemoveImage: vi.fn(),
        onMerge: vi.fn(),
        onDelete: vi.fn(),
        onDiscardDraft: vi.fn(),
    };
    render(
        <I18nextProvider i18n={createI18n('en')}>
            <DropdownMenu open>
                <DropdownMenuTrigger>menu</DropdownMenuTrigger>
                <DropdownMenuContent>
                    <EntityMenuItems
                        entities={entities}
                        list={{id: 'l', name: 'L', ...list} as EntityList}
                        variant="dropdown"
                        actions={actions}
                    />
                </DropdownMenuContent>
            </DropdownMenu>
        </I18nextProvider>
    );

    return actions;
}

const items = () =>
    screen.getAllByRole('menuitem').map(i => i.getAttribute('data-testid'));

describe('EntityMenuItems', () => {
    it('lists the actions on one value', () => {
        const e = entityOf('a', {thumbnail: 'thumb.webp'});
        const actions = renderMenu([e]);
        expect(items()).toEqual([
            'entity-menu-edit',
            'entity-menu-reject',
            'entity-menu-preview',
            'entity-menu-image-change',
            'entity-menu-image-remove',
            'entity-menu-delete',
        ]);
        fireEvent.click(screen.getByTestId('entity-menu-reject'));
        expect(actions.onSetStatus).toHaveBeenCalledWith(
            [e],
            AttributeEntityStatus.Rejected
        );
    });

    it('has no photo action without photo on the list', () => {
        renderMenu([entityOf('a', {status: AttributeEntityStatus.Pending})], {
            withImages: false,
        });
        expect(items()).toEqual([
            'entity-menu-edit',
            'entity-menu-approve',
            'entity-menu-reject',
            'entity-menu-preview',
            'entity-menu-delete',
        ]);
    });

    it('lists the bulk actions on several values', () => {
        const entities = [entityOf('a'), entityOf('b')];
        const actions = renderMenu(entities);
        expect(screen.getByTestId('entity-menu-count').textContent).toBe(
            '2 values selected'
        );
        expect(items()).toEqual([
            'entity-menu-approve',
            'entity-menu-reject',
            'entity-menu-merge',
            'entity-menu-delete',
        ]);
        fireEvent.click(screen.getByTestId('entity-menu-merge'));
        expect(actions.onMerge).toHaveBeenCalledWith(entities);
    });

    it('only discards a draft', () => {
        const draft = entityOf(`${DRAFT_PREFIX}1`);
        const actions = renderMenu([draft]);
        expect(items()).toEqual(['entity-menu-discard-draft']);
        fireEvent.click(screen.getByTestId('entity-menu-discard-draft'));
        expect(actions.onDiscardDraft).toHaveBeenCalledWith(draft);
    });
});
