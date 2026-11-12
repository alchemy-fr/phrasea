import {beforeEach, describe, expect, it, vi} from 'vitest';
import {
    act,
    fireEvent,
    render,
    screen,
    waitFor,
    within,
} from '@testing-library/react';
import {I18nextProvider} from 'react-i18next';
import {QueryClient, QueryClientProvider} from '@tanstack/react-query';
import {createI18n} from '../test/i18n';
import {createFakeGroupsApi} from '../test/groups';
import {ModalProvider} from '../modals/ModalProvider';
import {ManageGroupsDialog} from './ManageGroupsDialog';
import type {GroupMember, GroupsApi} from './types';

const toast = vi.hoisted(() => ({success: vi.fn(), error: vi.fn()}));
vi.mock('sonner', () => ({toast}));
vi.mock('next/navigation', () => ({usePathname: () => '/'}));

// cmdk needs them, jsdom has none
globalThis.ResizeObserver ??= class {
    observe() {}
    unobserve() {}
    disconnect() {}
} as unknown as typeof ResizeObserver;
Element.prototype.scrollIntoView ??= () => {};
globalThis.IntersectionObserver ??= class {
    observe() {}
    unobserve() {}
    disconnect() {}
} as unknown as typeof IntersectionObserver;

const alice: GroupMember = {
    id: 'u-alice',
    username: 'alice',
    firstName: 'Alice',
    lastName: 'Martin',
    email: 'alice@example.com',
};
const bob: GroupMember = {id: 'u-bob', username: 'bob'};

function setup() {
    const fake = createFakeGroupsApi(
        [
            {id: 'g-marketing', name: 'Marketing'},
            {id: 'g-sales', name: 'Sales'},
        ],
        {'g-marketing': [alice]},
        [alice, bob]
    );
    const api: GroupsApi = {
        ...fake.api,
        renameGroup: vi.fn(fake.api.renameGroup),
        deleteGroup: vi.fn(fake.api.deleteGroup),
        addMember: vi.fn(fake.api.addMember),
        removeMember: vi.fn(fake.api.removeMember),
    };
    const queryClient = new QueryClient({
        defaultOptions: {queries: {retry: false}},
    });
    const invalidate = vi.spyOn(queryClient, 'invalidateQueries');
    render(
        <QueryClientProvider client={queryClient}>
            <I18nextProvider i18n={createI18n('en')}>
                <ModalProvider>
                    <ManageGroupsDialog
                        open
                        onOpenChange={() => undefined}
                        api={api}
                    />
                </ModalProvider>
            </I18nextProvider>
        </QueryClientProvider>
    );

    return {api, state: fake.state, invalidate};
}

async function selectGroup(name: string) {
    fireEvent.click(await screen.findByRole('option', {name}));
    await screen.findByTestId('group-panel');
}

const nameInput = () => screen.getByTestId('group-name') as HTMLInputElement;

function invalidatedKeys(invalidate: {mock: {calls: unknown[][]}}) {
    return invalidate.mock.calls.map(
        c => (c[0] as {queryKey: unknown[]}).queryKey
    );
}

describe('ManageGroupsDialog', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('lists the groups and the members of the selected one', async () => {
        setup();

        expect(await screen.findByRole('option', {name: 'Sales'})).toBeTruthy();
        expect(
            screen.getByText('Select a group to manage its members.')
        ).toBeTruthy();

        await selectGroup('Marketing');

        expect(nameInput().value).toBe('Marketing');
        expect(await screen.findByText('Alice Martin')).toBeTruthy();
        expect(screen.getByText('alice@example.com')).toBeTruthy();
    });

    it('filters the groups by name', async () => {
        setup();
        await screen.findByRole('option', {name: 'Sales'});

        fireEvent.change(screen.getByTestId('groups-search'), {
            target: {value: 'mark'},
        });

        await waitFor(() =>
            expect(screen.queryByRole('option', {name: 'Sales'})).toBeNull()
        );
        expect(
            await screen.findByRole('option', {name: 'Marketing'})
        ).toBeTruthy();
    });

    it('renames a group and refreshes every list of groups', async () => {
        const {api, invalidate} = setup();
        await selectGroup('Sales');

        fireEvent.change(nameInput(), {target: {value: ' Sales team '}});
        fireEvent.click(screen.getByTestId('group-rename'));

        await waitFor(() =>
            expect(api.renameGroup).toHaveBeenCalledWith(
                'g-sales',
                'Sales team'
            )
        );
        expect(
            await screen.findByRole('option', {name: 'Sales team'})
        ).toBeTruthy();
        expect(invalidatedKeys(invalidate)).toEqual(
            expect.arrayContaining([['keycloak-groups'], ['groups']])
        );
        expect(toast.success).toHaveBeenCalledWith('Group renamed');
    });

    it('shows a taken name under the name field', async () => {
        setup();
        await selectGroup('Sales');

        fireEvent.change(nameInput(), {target: {value: 'Marketing'}});
        fireEvent.click(screen.getByTestId('group-rename'));

        expect(
            await screen.findByText('A group with this name already exists.')
        ).toBeTruthy();
        expect(nameInput().getAttribute('aria-invalid')).toBe('true');
        expect(toast.error).not.toHaveBeenCalled();
    });

    it('creates a group from the search and selects it', async () => {
        const {state} = setup();
        await screen.findByRole('option', {name: 'Sales'});
        fireEvent.change(screen.getByTestId('groups-search'), {
            target: {value: 'Design'},
        });

        fireEvent.click(screen.getByTestId('groups-new'));
        const field = (await screen.findByTestId(
            'create-group-name'
        )) as HTMLInputElement;
        expect(field.value).toBe('Design');
        fireEvent.click(screen.getByRole('button', {name: 'Create'}));

        await waitFor(() => expect(nameInput().value).toBe('Design'));
        expect(state.groups.map(g => g.name)).toContain('Design');
        expect(toast.success).toHaveBeenCalledWith('Group created');
    });

    it('deletes a group once its name is typed', async () => {
        const {api, state} = setup();
        await selectGroup('Sales');

        fireEvent.click(screen.getByTestId('group-delete'));
        const dialog = within(await screen.findByTestId('form-dialog'));
        const confirm = dialog.getByRole('button', {name: 'Delete'});
        expect(confirm.hasAttribute('disabled')).toBe(true);

        fireEvent.change(dialog.getByRole('textbox'), {
            target: {value: 'Sales'},
        });
        act(() => confirm.click());

        await waitFor(() =>
            expect(api.deleteGroup).toHaveBeenCalledWith('g-sales')
        );
        await waitFor(() =>
            expect(screen.queryByTestId('group-panel')).toBeNull()
        );
        expect(state.groups.map(g => g.name)).toEqual(['Marketing']);
    });

    it('removes a member after confirmation', async () => {
        const {api, state} = setup();
        await selectGroup('Marketing');
        await screen.findByText('Alice Martin');

        fireEvent.click(screen.getByTestId('group-member-remove'));
        expect(
            await screen.findByText('Remove Alice Martin from the group?')
        ).toBeTruthy();
        const dialog = within(screen.getByTestId('form-dialog'));
        act(() => dialog.getByRole('button', {name: 'Remove'}).click());

        await waitFor(() =>
            expect(api.removeMember).toHaveBeenCalledWith(
                'g-marketing',
                'u-alice'
            )
        );
        expect(await screen.findByText('No member')).toBeTruthy();
        expect(state.members['g-marketing']).toEqual([]);
    });

    it('adds a member picked among the users', async () => {
        const {api} = setup();
        await selectGroup('Sales');
        expect(await screen.findByText('No member')).toBeTruthy();

        fireEvent.click(screen.getByRole('combobox'));
        fireEvent.click(await screen.findByText('bob'));

        await waitFor(() =>
            expect(api.addMember).toHaveBeenCalledWith('g-sales', 'u-bob')
        );
        expect(await screen.findByTestId('group-member')).toBeTruthy();
    });
});
