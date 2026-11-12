import {afterEach, describe, expect, it, vi} from 'vitest';
import {cleanup, render, waitFor} from '@testing-library/react';
import {RealtimeProvider, useChannelEvent} from './RealtimeProvider';

const state = vi.hoisted(() => ({
    status: 'loading' as 'loading' | 'anonymous' | 'authenticated',
    pusher: {
        subscribe: vi.fn(() => ({bind: vi.fn(), unbind: vi.fn()})),
        unsubscribe: vi.fn(),
        disconnect: vi.fn(),
        connect: vi.fn(),
        connection: {bind: vi.fn()},
    },
}));

vi.mock('pusher-js', () => ({
    default: vi.fn(function () {
        return state.pusher;
    }),
}));
vi.mock('@/lib/config/ConfigProvider', () => ({
    useConfig: () => ({realtime: {host: 'ws.phrasea.test', key: 'key'}}),
}));
vi.mock('@/lib/auth/AuthProvider', () => ({
    useAuth: () => ({status: state.status}),
}));
vi.mock('@/lib/api/http', () => ({api: {post: vi.fn()}}));

function Subscriber() {
    useChannelEvent('private-asset-a1', 'rendition-update', () => undefined);

    return null;
}

function renderProvider() {
    return render(
        <RealtimeProvider>
            <Subscriber />
        </RealtimeProvider>
    );
}

describe('RealtimeProvider', () => {
    afterEach(() => {
        cleanup();
        vi.clearAllMocks();
    });

    it('authorizes the channels again once the visitor logs in or out', async () => {
        state.status = 'anonymous';
        const view = renderProvider();
        await waitFor(() =>
            expect(state.pusher.subscribe).toHaveBeenCalledWith(
                'private-asset-a1'
            )
        );
        expect(state.pusher.disconnect).not.toHaveBeenCalled();

        state.status = 'authenticated';
        view.rerender(
            <RealtimeProvider>
                <Subscriber />
            </RealtimeProvider>
        );
        expect(state.pusher.disconnect).toHaveBeenCalledTimes(1);
        expect(state.pusher.connect).toHaveBeenCalledTimes(1);

        state.status = 'anonymous';
        view.rerender(
            <RealtimeProvider>
                <Subscriber />
            </RealtimeProvider>
        );
        expect(state.pusher.disconnect).toHaveBeenCalledTimes(2);
        expect(state.pusher.connect).toHaveBeenCalledTimes(2);
    });
});
