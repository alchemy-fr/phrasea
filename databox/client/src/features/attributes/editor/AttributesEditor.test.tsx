import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';
import {cleanup, fireEvent, render} from '@testing-library/react';
import {I18nextProvider} from 'react-i18next';
import {createI18n} from '@/i18n';
import {AssetTypeFilter, type AttributeDefinition} from '@/types/api';
import {NO_LOCALE} from '@/lib/utils/locale';
import {AttributesEditor} from './AttributesEditor';
import type {AttributeIndex, DefinitionIndex} from './attributeEditorModel';

function definition(
    id: string,
    position: number,
    extra: Partial<AttributeDefinition> = {}
): AttributeDefinition {
    return {
        id,
        slug: id,
        name: id,
        type: 'text',
        workspace: '/workspaces/ws',
        editable: true,
        editableInGui: true,
        canEdit: true,
        enabled: true,
        position,
        ...extra,
    } as unknown as AttributeDefinition;
}

const definitions: DefinitionIndex = {
    d1: definition('d1', 1),
    d2: definition('d2', 2, {canEdit: false}),
    d3: definition('d3', 3),
    d4: definition('d4', 4, {multiple: true}),
};

const attributes: AttributeIndex = {
    d4: {[NO_LOCALE]: [{id: 'v1', value: 'one'}]},
};

const i18n = createI18n('en');

function editor(props: Partial<Parameters<typeof AttributesEditor>[0]> = {}) {
    return (
        <I18nextProvider i18n={i18n}>
            <AttributesEditor
                attributes={attributes}
                definitions={definitions}
                onChange={() => {}}
                target={AssetTypeFilter.Asset}
                {...props}
            />
        </I18nextProvider>
    );
}

const byId = (id: string) => document.getElementById(id) as HTMLElement;

function addButton(): HTMLElement {
    return Array.from(document.querySelectorAll('button')).find(b =>
        b.textContent?.includes('Add d4')
    )!;
}

/** Presses Tab on `el`, returns whether the browser behavior was kept */
function tab(el: HTMLElement, shiftKey = false): boolean {
    el.focus();

    return fireEvent.keyDown(el, {key: 'Tab', shiftKey});
}

describe('AttributesEditor', () => {
    beforeEach(() => {
        vi.stubGlobal(
            'IntersectionObserver',
            class {
                observe() {}
                unobserve() {}
                disconnect() {}
            }
        );
    });

    afterEach(() => {
        cleanup();
        vi.unstubAllGlobals();
    });

    it('tabs from field to field, skipping the read only one', () => {
        render(editor());

        expect(tab(byId('attr-d1'))).toBe(false);
        expect(document.activeElement).toBe(byId('attr-d3'));

        tab(byId('attr-d3'));
        expect(document.activeElement).toBe(byId('attr-d4-0'));

        // Over the "Remove" button of the value
        tab(byId('attr-d4-0'));
        expect(document.activeElement).toBe(addButton());
    });

    it('goes back with Shift+Tab', () => {
        render(editor());

        tab(byId('attr-d4-0'), true);
        expect(document.activeElement).toBe(byId('attr-d3'));

        tab(byId('attr-d3'), true);
        expect(document.activeElement).toBe(byId('attr-d1'));

        expect(tab(byId('attr-d1'), true)).toBe(true);
        expect(document.activeElement).toBe(byId('attr-d1'));
    });

    it('leaves Tab to the browser after the last field', () => {
        const onLoadMore = vi.fn();
        render(editor({onLoadMore}));

        expect(tab(addButton())).toBe(true);
        expect(document.activeElement).toBe(addButton());
        expect(onLoadMore).not.toHaveBeenCalled();
    });

    it('loads the next fields and focuses the first of them', () => {
        const onLoadMore = vi.fn();
        const {rerender} = render(editor({hasMore: true, onLoadMore}));

        expect(tab(addButton())).toBe(false);
        expect(onLoadMore).toHaveBeenCalledTimes(1);

        rerender(
            editor({
                definitions: {...definitions, d5: definition('d5', 5)},
                onLoadMore,
            })
        );
        expect(document.activeElement).toBe(byId('attr-d5'));
    });
});
