import type {ReactNode} from 'react';
import {beforeEach, describe, expect, it, vi} from 'vitest';
import {act, fireEvent, render, screen} from '@testing-library/react';
import {I18nextProvider} from 'react-i18next';
import {QueryClient, QueryClientProvider} from '@tanstack/react-query';
import {createI18n} from '@/i18n';
import {AttributeType} from '@/types/api';
import {useDefinitionsStore} from '@/features/attributes/definitionsStore';
import {SearchBar} from './SearchBar';

const search = vi.hoisted(() => ({
    query: '',
    conditions: [] as {id: string; query: string}[],
    geolocation: undefined,
    inputQuery: {current: ''},
    setInputQuery: vi.fn(),
    setQuery: vi.fn(),
    setGeolocation: vi.fn(),
    upsertCondition: vi.fn(),
    selectCollection: vi.fn(),
    selectWorkspace: vi.fn(),
}));
const api = vi.hoisted(() => ({
    getSearchSuggestions: vi.fn(async () => ({items: []})),
}));

vi.mock('./SearchProvider', () => ({useSearch: () => search}));
vi.mock('./ResultProvider', () => ({
    useResults: () => ({loading: false, facets: undefined}),
}));
vi.mock('@/lib/api/assets', () => api);
vi.mock('./SearchMoreMenu', () => ({SearchMoreMenu: () => null}));
vi.mock('./sort/SortByButton', () => ({SortByButton: () => null}));
vi.mock('@/hooks/useBrowserLocation', () => ({
    useBrowserLocation: () => ({requestLocation: vi.fn(), loading: false}),
}));
vi.mock('@/components/ui/overlays', () => ({
    Tooltip: ({children}: {children: ReactNode}) => children,
}));

function renderBar() {
    act(() => {
        render(
            <I18nextProvider i18n={createI18n('en')}>
                <QueryClientProvider client={new QueryClient()}>
                    <SearchBar />
                </QueryClientProvider>
            </I18nextProvider>
        );
    });

    return screen.getByTestId('search-input') as HTMLInputElement;
}

function type(input: HTMLInputElement, value: string) {
    act(() => {
        fireEvent.focus(input);
        fireEvent.change(input, {target: {value}});
    });
}

function options(): string[] {
    return screen
        .queryAllByRole('option')
        .map(o => (o.querySelector('span') as HTMLElement).textContent ?? '');
}

describe('SearchBar filter suggestions', () => {
    beforeEach(() => {
        search.conditions = [];
        search.upsertCondition.mockReset();
        search.setInputQuery.mockReset();
        search.setQuery.mockReset();
        useDefinitionsStore.setState({
            loaded: true,
            builtIn: [
                {
                    id: '@story',
                    name: '@story',
                    slug: '@story',
                    searchSlug: '@story',
                    displayName: 'Stories',
                    type: AttributeType.Story,
                    builtIn: true,
                    enabled: true,
                    searchable: true,
                },
                {
                    id: '@isStory',
                    name: '@isStory',
                    slug: '@isStory',
                    searchSlug: '@isStory',
                    displayName: 'Is story',
                    type: AttributeType.Boolean,
                    builtIn: true,
                    enabled: true,
                    searchable: true,
                },
            ] as any,
            definitions: [
                {
                    id: 'd1',
                    name: 'Title',
                    slug: 'title',
                    searchSlug: 'title_text_s',
                    displayName: 'Title',
                    type: AttributeType.Text,
                    enabled: true,
                    searchable: true,
                },
            ] as any,
        });
    });

    it('suggests the fields matching a word, then the values of the field', () => {
        const input = renderBar();
        type(input, 'story');
        expect(options()).toEqual(['@story:', '@isStory:']);

        act(() => {
            fireEvent.click(screen.getAllByRole('option')[1]);
        });
        expect(input.value).toBe('@isStory:');
        expect(search.setInputQuery).toHaveBeenLastCalledWith('@isStory:');
        expect(options()).toEqual(['Yes', 'No']);

        act(() => {
            fireEvent.click(screen.getAllByRole('option')[0]);
        });
        expect(search.upsertCondition).toHaveBeenCalledWith({
            id: '@isStory',
            query: '@isStory IS true',
            resetQuery: true,
        });
        expect(input.value).toBe('');
        expect(search.setQuery).not.toHaveBeenCalled();
    });

    it('applies the highlighted value on Enter', () => {
        const input = renderBar();
        type(input, '@isStory:');
        expect(options()).toEqual(['Yes', 'No']);

        act(() => {
            fireEvent.keyDown(input, {key: 'ArrowDown'});
        });
        act(() => {
            fireEvent.keyDown(input, {key: 'ArrowDown'});
        });
        act(() => {
            fireEvent.keyDown(input, {key: 'Enter'});
        });
        expect(search.upsertCondition).toHaveBeenCalledWith({
            id: '@isStory',
            query: '@isStory IS false',
            resetQuery: true,
        });
    });

    it('applies the only value left by the typed prefix on Enter', () => {
        const input = renderBar();
        type(input, '@isStory:tr');
        expect(options()).toEqual(['Yes']);

        act(() => {
            fireEvent.keyDown(input, {key: 'Enter'});
        });
        expect(search.upsertCondition).toHaveBeenCalledWith({
            id: '@isStory',
            query: '@isStory IS true',
            resetQuery: true,
        });
    });

    it('turns a typed text value into a condition on Enter', () => {
        const input = renderBar();
        type(input, 'title:chat');
        expect(options()).toEqual(['title IS "chat"']);

        act(() => {
            fireEvent.keyDown(input, {key: 'Enter'});
        });
        expect(search.upsertCondition).toHaveBeenCalledWith({
            id: 'title_text_s',
            query: 'title IS "chat"',
            resetQuery: true,
        });
        expect(search.setQuery).not.toHaveBeenCalled();
        expect(input.value).toBe('');
    });

    it('never submits a field being typed as a text search', () => {
        const input = renderBar();
        type(input, '@isStory:');
        act(() => {
            fireEvent.keyDown(input, {key: 'Enter'});
        });
        expect(search.setQuery).not.toHaveBeenCalled();
        expect(search.upsertCondition).not.toHaveBeenCalled();
        expect(input.value).toBe('@isStory:');
    });

    it('does not query the API for text terms while a field is typed', () => {
        const input = renderBar();
        type(input, '@isStory:');
        expect(api.getSearchSuggestions).not.toHaveBeenCalled();
    });
});
