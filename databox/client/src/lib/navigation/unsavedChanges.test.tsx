import React from 'react';
import {afterEach, describe, expect, it} from 'vitest';
import {act, renderHook} from '@testing-library/react';
import {
    askDiscardChanges,
    confirmLeave,
    hasUnsavedChanges,
    UnsavedChangesScope,
    useDirtyState,
    useUnsavedChangesStore,
} from './unsavedChanges';

afterEach(() => {
    useUnsavedChangesStore.setState({dirty: {}, pending: null});
});

describe('useDirtyState', () => {
    it('is dirty only while the values differ from the snapshot', () => {
        const {result, rerender} = renderHook(
            ({values}) => useDirtyState(values),
            {initialProps: {values: {a: 1, b: 'x'} as object}}
        );
        expect(result.current.dirty).toBe(false);
        expect(hasUnsavedChanges()).toBe(false);

        rerender({values: {a: 2, b: 'x'}});
        expect(result.current.dirty).toBe(true);
        expect(hasUnsavedChanges()).toBe(true);

        // Key order, undefined and empty keys do not count
        rerender({values: {b: 'x', a: 1, c: undefined, d: ''}});
        expect(result.current.dirty).toBe(false);
        expect(hasUnsavedChanges()).toBe(false);
    });

    it('takes the saved values as the new reference', () => {
        const {result, rerender} = renderHook(
            ({values}) => useDirtyState(values),
            {initialProps: {values: {a: 1}}}
        );
        rerender({values: {a: 2}});
        act(() => result.current.markSaved());
        expect(result.current.dirty).toBe(false);

        rerender({values: {a: 1}});
        expect(result.current.dirty).toBe(true);
    });

    it('waits for the values to be ready before taking the snapshot', () => {
        const {result, rerender} = renderHook(
            ({values, ready}) => useDirtyState(values, {ready}),
            {initialProps: {values: {a: ''}, ready: false}}
        );
        rerender({values: {a: 'loaded'}, ready: true});
        expect(result.current.dirty).toBe(false);

        rerender({values: {a: 'edited'}, ready: true});
        expect(result.current.dirty).toBe(true);
    });

    it('unregisters when unmounted', () => {
        const {rerender, unmount} = renderHook(
            ({values}) => useDirtyState(values),
            {initialProps: {values: {a: 1}}}
        );
        rerender({values: {a: 2}});
        expect(hasUnsavedChanges()).toBe(true);

        unmount();
        expect(hasUnsavedChanges()).toBe(false);
    });
});

describe('scopes', () => {
    it('a scope contains the scopes nested in it', () => {
        const wrapper = ({children}: {children: React.ReactNode}) => (
            <UnsavedChangesScope.Provider value="/dialog/form">
                {children}
            </UnsavedChangesScope.Provider>
        );
        const {rerender} = renderHook(({values}) => useDirtyState(values), {
            initialProps: {values: {a: 1}},
            wrapper,
        });
        rerender({values: {a: 2}});

        expect(hasUnsavedChanges('/dialog/form')).toBe(true);
        expect(hasUnsavedChanges('/dialog')).toBe(true);
        expect(hasUnsavedChanges('/other')).toBe(false);
        expect(hasUnsavedChanges('/dia')).toBe(false);
        expect(hasUnsavedChanges()).toBe(true);
    });
});

describe('confirmLeave', () => {
    it('resolves right away when nothing is dirty', async () => {
        await expect(confirmLeave()).resolves.toBe(true);
        expect(useUnsavedChangesStore.getState().pending).toBeNull();
    });

    it('asks, and lets the next navigation through once discarded', async () => {
        useUnsavedChangesStore.setState({dirty: {form: ''}});
        const answer = confirmLeave();
        const {pending} = useUnsavedChangesStore.getState();
        expect(pending).not.toBeNull();

        act(() => pending!.resolve(true));
        await expect(answer).resolves.toBe(true);
        expect(useUnsavedChangesStore.getState().pending).toBeNull();
        // Grace period: the navigation that follows is not asked again
        expect(hasUnsavedChanges()).toBe(false);
    });

    it('dismisses a previous question when a new one is asked', async () => {
        const first = askDiscardChanges();
        const second = askDiscardChanges();
        await expect(first).resolves.toBe(false);

        act(() => useUnsavedChangesStore.getState().pending!.resolve(false));
        await expect(second).resolves.toBe(false);
    });
});
