import {beforeEach, describe, expect, it, vi} from 'vitest';
import {fireEvent, render, screen, waitFor} from '@testing-library/react';
import {I18nextProvider} from 'react-i18next';
import {QueryClient, QueryClientProvider} from '@tanstack/react-query';
import {createI18n} from '../test/i18n';
import {createFakeGroupsApi} from '../test/groups';
import {ApiError} from '../utils/errors';
import {CreateGroupDialog, getNameError} from './CreateGroupDialog';
import type {GroupsApi} from './types';

const toast = vi.hoisted(() => ({success: vi.fn(), error: vi.fn()}));
vi.mock('sonner', () => ({toast}));

function renderDialog(api: GroupsApi, initialName?: string) {
    const resolve = vi.fn();
    render(
        <QueryClientProvider client={new QueryClient()}>
            <I18nextProvider i18n={createI18n('en')}>
                <CreateGroupDialog
                    open
                    onOpenChange={() => undefined}
                    resolve={resolve}
                    api={api}
                    initialName={initialName}
                />
            </I18nextProvider>
        </QueryClientProvider>
    );

    return {resolve};
}

const field = () => screen.getByLabelText('Name') as HTMLInputElement;

describe('CreateGroupDialog', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('creates the group named as typed', async () => {
        const {api} = createFakeGroupsApi([]);
        const {resolve} = renderDialog(api, 'Design');

        expect(field().value).toBe('Design');
        fireEvent.change(field(), {target: {value: '  Design team '}});
        fireEvent.click(screen.getByRole('button', {name: 'Create'}));

        await waitFor(() => expect(resolve).toHaveBeenCalled());
        expect(resolve.mock.lastCall?.[0]).toMatchObject({
            name: 'Design team',
        });
        expect(toast.success).toHaveBeenCalledWith('Group created');
    });

    it('shows a taken name under the field', async () => {
        const {api} = createFakeGroupsApi([{id: 'g1', name: 'Design'}]);
        const {resolve} = renderDialog(api, 'Design');

        fireEvent.click(screen.getByRole('button', {name: 'Create'}));

        expect(
            await screen.findByText('A group with this name already exists.')
        ).toBeTruthy();
        expect(field().getAttribute('aria-invalid')).toBe('true');
        expect(resolve).not.toHaveBeenCalled();
        expect(toast.error).not.toHaveBeenCalled();
    });

    it('reports the other failures', async () => {
        const {api} = createFakeGroupsApi([]);
        const {resolve} = renderDialog(
            {
                ...api,
                createGroup: async () => {
                    throw new ApiError('Forbidden', 403, {});
                },
            },
            'Design'
        );

        fireEvent.click(screen.getByRole('button', {name: 'Create'}));

        await waitFor(() =>
            expect(toast.error).toHaveBeenCalledWith('Forbidden')
        );
        expect(resolve).not.toHaveBeenCalled();
    });
});

describe('getNameError', () => {
    it('reads the violations of the name only', () => {
        const violations = [
            {propertyPath: 'name', message: 'Too long'},
            {propertyPath: 'other', message: 'Other'},
        ];

        expect(getNameError(new ApiError('', 400, {violations}))).toBe(
            'Too long'
        );
        expect(
            getNameError(new ApiError('', 403, {violations}))
        ).toBeUndefined();
        expect(getNameError(new Error('nope'))).toBeUndefined();
    });
});
