import {afterEach, describe, expect, it, vi} from 'vitest';
import {act, cleanup, render} from '@testing-library/react';
import type {Asset} from '@/types/api';
import {useAssetStore} from '@/features/assets/assetStore';
import {
    RenditionUpdateFeed,
    useRenditionUpdate,
    useWatchWorkspaces,
    type RenditionUpdate,
} from './renditionUpdates';

const realtime = vi.hoisted(() => ({
    channels: new Map<string, (data: unknown) => void>(),
}));

vi.mock('@/lib/realtime/RealtimeProvider', async () => {
    const {useEffect, useRef} = await import('react');

    return {
        useChannelEvent: (
            channel: string,
            event: string,
            callback: (data: unknown) => void
        ) => {
            const cbRef = useRef(callback);
            cbRef.current = callback;
            useEffect(() => {
                const key = `${channel}|${event}`;
                realtime.channels.set(key, data => cbRef.current(data));

                return () => {
                    realtime.channels.delete(key);
                };
            }, [channel, event]);
        },
    };
});

function asset(id: string, workspaceId: string): Asset {
    return {id, workspace: {id: workspaceId}} as unknown as Asset;
}

function subscribed(): string[] {
    return [...realtime.channels.keys()].sort();
}

function Consumer({onUpdate}: {onUpdate: (e: RenditionUpdate) => void}) {
    useRenditionUpdate(onUpdate);

    return null;
}

function Watcher({ids}: {ids: string[]}) {
    useWatchWorkspaces(ids);

    return null;
}

describe('rendition updates', () => {
    afterEach(() => {
        cleanup();
        useAssetStore.setState({assets: {}});
    });

    it('follows the workspaces of the displayed assets', () => {
        const onUpdate = vi.fn();
        render(
            <>
                <RenditionUpdateFeed />
                <Consumer onUpdate={onUpdate} />
            </>
        );
        expect(subscribed()).toEqual([]);

        act(() =>
            useAssetStore
                .getState()
                .setAssets([
                    asset('a1', 'ws1'),
                    asset('a2', 'ws1'),
                    asset('a3', 'ws2'),
                ])
        );
        expect(subscribed()).toEqual([
            'private-workspace-ws1|rendition-update',
            'private-workspace-ws2|rendition-update',
        ]);

        act(() =>
            realtime.channels.get('private-workspace-ws1|rendition-update')!({
                assetId: 'a2',
            })
        );
        expect(onUpdate).toHaveBeenCalledExactlyOnceWith({assetId: 'a2'});

        act(() => useAssetStore.getState().remove(['a3']));
        expect(subscribed()).toEqual([
            'private-workspace-ws1|rendition-update',
        ]);
    });

    it('follows the workspaces declared by a screen while it is mounted', () => {
        render(<RenditionUpdateFeed />);

        const first = render(<Watcher ids={['ws3', 'ws4']} />);
        const second = render(<Watcher ids={['ws3']} />);
        expect(subscribed()).toEqual([
            'private-workspace-ws3|rendition-update',
            'private-workspace-ws4|rendition-update',
        ]);

        first.unmount();
        expect(subscribed()).toEqual([
            'private-workspace-ws3|rendition-update',
        ]);

        second.unmount();
        expect(subscribed()).toEqual([]);
    });

    it('stops calling back an unmounted consumer', () => {
        const onUpdate = vi.fn();
        render(<RenditionUpdateFeed />);
        const consumer = render(<Consumer onUpdate={onUpdate} />);
        act(() => useAssetStore.getState().setAssets([asset('a1', 'ws1')]));

        consumer.unmount();
        act(() =>
            realtime.channels.get('private-workspace-ws1|rendition-update')!({
                assetId: 'a1',
            })
        );
        expect(onUpdate).not.toHaveBeenCalled();
    });
});
