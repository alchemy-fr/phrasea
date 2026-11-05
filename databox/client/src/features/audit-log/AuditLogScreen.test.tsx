import {beforeEach, describe, expect, it, vi} from 'vitest';
import {fireEvent, render, screen, waitFor} from '@testing-library/react';
import {I18nextProvider} from 'react-i18next';
import {QueryClient, QueryClientProvider} from '@tanstack/react-query';
import {Tooltip as TooltipPrimitive} from 'radix-ui';
import {createI18n} from '@/i18n';
import {type AuditLog, AuditLogActorType} from '@/types/api';
import {AuditLogScreen} from './AuditLogScreen';

const {nav, api} = vi.hoisted(() => ({
    nav: {
        params: new URLSearchParams(),
        replace: vi.fn(),
    },
    api: {
        getAuditLogs: vi.fn(),
        getUsers: vi.fn(),
    },
}));

vi.mock('next/navigation', () => ({
    usePathname: () => '/admin/audit-log',
    useSearchParams: () => nav.params,
    useRouter: () => ({push: vi.fn(), replace: nav.replace}),
}));

vi.mock('next/link', () => ({
    default: ({href, children, className}: any) => (
        <a href={typeof href === 'string' ? href : '#'} className={className}>
            {children}
        </a>
    ),
}));

vi.mock('@/lib/auth/AuthProvider', () => ({
    useAuth: () => ({
        status: 'authenticated',
        isAuthenticated: true,
        hasRole: () => true,
        login: vi.fn(),
        logout: vi.fn(),
    }),
    AppRole: {},
}));

vi.mock('@/lib/api/auditLogs', async importOriginal => {
    const actual = await importOriginal<typeof import('@/lib/api/auditLogs')>();

    return {...actual, getAuditLogs: api.getAuditLogs};
});

vi.mock('@/lib/api/collections', async importOriginal => {
    const actual =
        await importOriginal<typeof import('@/lib/api/collections')>();

    return {
        ...actual,
        getWorkspaces: () => Promise.resolve({total: 0, items: []}),
    };
});

vi.mock('@/lib/api/misc', async importOriginal => {
    const actual = await importOriginal<typeof import('@/lib/api/misc')>();

    return {...actual, getUsers: api.getUsers};
});

const logs: AuditLog[] = [
    {
        id: 'l1',
        date: '2026-03-02T10:00:00+00:00',
        action: 'asset_attribute_changed',
        actorType: AuditLogActorType.User,
        user: {id: 'u1', username: 'alice'},
        impersonatorId: 'admin-1',
        objectType: 'asset',
        objectId: 'a1',
        objectLabel: 'Sunset',
        correlationId: 'c0ffee00-0000-4000-8000-000000000000',
        data: {
            changes: [
                {
                    definitionId: 'd1',
                    definitionName: 'Title',
                    locale: 'en',
                    op: 'set',
                    old: ['Old title'],
                    new: ['New title'],
                },
            ],
        },
        meta: {source: 'api'},
    },
    {
        id: 'l2',
        date: '2026-03-01T10:00:00+00:00',
        action: 'object_trashed',
        actorType: AuditLogActorType.System,
        objectType: 'collection',
        objectId: 'c1',
        objectLabel: 'Archives',
        data: [],
        meta: {source: 'worker'},
    },
];

function renderScreen() {
    const queryClient = new QueryClient({
        defaultOptions: {queries: {retry: false}},
    });

    return render(
        <QueryClientProvider client={queryClient}>
            <I18nextProvider i18n={createI18n('en')}>
                <TooltipPrimitive.Provider>
                    <AuditLogScreen />
                </TooltipPrimitive.Provider>
            </I18nextProvider>
        </QueryClientProvider>
    );
}

describe('AuditLogScreen', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        nav.params = new URLSearchParams();
        api.getAuditLogs.mockResolvedValue({total: 2, items: logs});
        api.getUsers.mockResolvedValue([{id: 'admin-1', username: 'root'}]);
    });

    it('lists the entries with their actor, action and object', async () => {
        renderScreen();

        await waitFor(() =>
            expect(screen.getAllByTestId('audit-log-row')).toHaveLength(2)
        );
        const [first, second] = screen.getAllByTestId('audit-log-row');
        expect(first.textContent).toContain('alice');
        expect(first.textContent).toContain('Attributes changed');
        expect(first.textContent).toContain('Sunset');
        expect(first.querySelector('a')?.getAttribute('href')).toBe(
            '/assets/a1/_'
        );
        expect(second.textContent).toContain('System');
        expect(second.textContent).toContain('Moved to the trash');

        // The impersonators of the page, resolved at once
        await waitFor(() => expect(first.textContent).toContain('via root'));
        expect(api.getUsers).toHaveBeenCalledTimes(1);
        expect(api.getUsers).toHaveBeenCalledWith({ids: ['admin-1']});
    });

    it('reads the filters from the URL', async () => {
        nav.params = new URLSearchParams(
            'action=asset_moved&action=object_updated&workspaceId=ws-1&from=2026-01-01'
        );
        renderScreen();

        await waitFor(() => expect(api.getAuditLogs).toHaveBeenCalled());
        const [filters, next] = api.getAuditLogs.mock.calls[0];
        expect(filters.actions).toEqual(['asset_moved', 'object_updated']);
        expect(filters.workspaceId).toBe('ws-1');
        expect(new Date(filters.dateAfter).getTime()).toBe(
            new Date('2026-01-01T00:00:00').getTime()
        );
        expect(next).toBeUndefined();
    });

    it('filters by the correlation of an entry', async () => {
        renderScreen();

        fireEvent.click(await screen.findByTestId('audit-log-correlation'));

        expect(nav.replace).toHaveBeenCalledWith(
            '/admin/audit-log?correlationId=c0ffee00-0000-4000-8000-000000000000',
            {scroll: false}
        );
    });

    it('details the attribute changes before and after', async () => {
        renderScreen();

        const [toggle] = await screen.findAllByTestId('audit-log-toggle');
        expect(screen.queryByTestId('audit-log-details')).toBeNull();
        fireEvent.click(toggle);

        const changes = screen.getByTestId('audit-log-changes');
        expect(changes.textContent).toContain('Title · en');
        expect(changes.textContent).toContain('Old title');
        expect(changes.textContent).toContain('New title');
    });
});
