'use client';

import {
    createContext,
    PropsWithChildren,
    useCallback,
    useContext,
    useEffect,
    useMemo,
    useRef,
    useState,
} from 'react';
import {LockIcon} from 'lucide-react';
import type {Asset} from '@/types/api';
import {
    useDisplayPreferences,
    usePreferencesStore,
} from '@/features/preferences/store';
import {FilePlayer} from '@/features/assets/player/FilePlayer';
import {AttributeList} from '@/features/attributes/AttributeList';
import {Button} from '@/components/ui/button';
import {useLiveAsset} from '@/features/assets/assetStore';
import {cn} from '@/lib/utils/cn';

type PreviewContextValue = {
    onEnter: (asset: Asset, anchor: HTMLElement) => void;
    onLeave: (asset: Asset) => void;
};

const PreviewContext = createContext<PreviewContextValue>({
    onEnter: () => undefined,
    onLeave: () => undefined,
});

export function usePreview(): PreviewContextValue {
    return useContext(PreviewContext);
}

/**
 * Hover preview of an asset (player + pinned attributes), positioned next to
 * the hovered thumbnail and kept inside the viewport. Clicking the lock makes
 * it interactive.
 */
export function PreviewProvider({
    children,
    disabled,
}: PropsWithChildren<{disabled?: boolean}>) {
    const display = useDisplayPreferences();
    const updatePreference = usePreferencesStore(s => s.updatePreference);
    const [state, setState] = useState<{
        asset: Asset;
        anchor: HTMLElement;
    } | null>(null);
    const [locked, setLocked] = useState(false);
    const enterTimer = useRef<ReturnType<typeof setTimeout>>(undefined);
    const leaveTimer = useRef<ReturnType<typeof setTimeout>>(undefined);
    const enabled = display.displayPreview && !disabled;

    const onEnter = useCallback(
        (asset: Asset, anchor: HTMLElement) => {
            if (!enabled || locked) {
                return;
            }
            clearTimeout(leaveTimer.current);
            clearTimeout(enterTimer.current);
            enterTimer.current = setTimeout(
                () => setState({asset, anchor}),
                50
            );
        },
        [enabled, locked]
    );
    const onLeave = useCallback(() => {
        if (locked) {
            return;
        }
        clearTimeout(enterTimer.current);
        leaveTimer.current = setTimeout(() => setState(null), 100);
    }, [locked]);

    useEffect(() => {
        if (display.previewLocked) {
            setLocked(true);
        }
    }, [display.previewLocked]);

    const value = useMemo(() => ({onEnter, onLeave}), [onEnter, onLeave]);

    return (
        <PreviewContext.Provider value={value}>
            {children}
            {state ? (
                <PreviewPopover
                    asset={state.asset}
                    anchor={state.anchor}
                    locked={locked}
                    onLockToggle={() => {
                        const next = !locked;
                        setLocked(next);
                        void updatePreference('display', prev => ({
                            ...display,
                            ...(prev ?? {}),
                            previewLocked: next,
                        }));
                        if (!next) {
                            setState(null);
                        }
                    }}
                    onMouseEnter={() => clearTimeout(leaveTimer.current)}
                    onMouseLeave={onLeave}
                />
            ) : null}
        </PreviewContext.Provider>
    );
}

function PreviewPopover({
    asset: initial,
    anchor,
    locked,
    onLockToggle,
    onMouseEnter,
    onMouseLeave,
}: {
    asset: Asset;
    anchor: HTMLElement;
    locked: boolean;
    onLockToggle: () => void;
    onMouseEnter: () => void;
    onMouseLeave: () => void;
}) {
    const asset = useLiveAsset(initial);
    const display = useDisplayPreferences();
    const {sizeRatio, attributesRatio, displayFile, displayAttributes} =
        display.previewOptions;
    const [pos, setPos] = useState<{
        top: number;
        left: number;
        width: number;
        height: number;
    } | null>(null);

    useEffect(() => {
        const compute = () => {
            const vw = window.innerWidth;
            const vh = window.innerHeight;
            const width = Math.min(vw * sizeRatio, vw - 32);
            const height = Math.min(vh * sizeRatio, vh - 32);
            const r = anchor.getBoundingClientRect();
            let left = r.right + 12;
            if (left + width > vw - 16) {
                left = r.left - width - 12;
            }
            if (left < 16) {
                left = Math.max(16, Math.min(vw - width - 16, r.left));
            }
            let top = r.top + r.height / 2 - height / 2;
            top = Math.max(16, Math.min(vh - height - 16, top));
            setPos({top, left, width, height});
        };
        compute();
        window.addEventListener('resize', compute);

        return () => window.removeEventListener('resize', compute);
    }, [anchor, sizeRatio]);

    if (!pos) {
        return null;
    }
    const file = asset.preview?.file ?? asset.thumbnail?.file;
    const showFile = displayFile && !!file;
    const showAttrs = displayAttributes;

    return (
        <div
            className={cn(
                'fixed z-40 flex overflow-hidden rounded-lg border bg-popover text-popover-foreground shadow-2xl animate-in fade-in-0 zoom-in-95',
                !locked && 'pointer-events-none'
            )}
            style={{
                top: pos.top,
                left: pos.left,
                width: pos.width,
                height: pos.height,
            }}
            onMouseEnter={onMouseEnter}
            onMouseLeave={onMouseLeave}
        >
            {showFile ? (
                <div
                    className="relative flex min-w-0 flex-1 items-center justify-center bg-media-bg"
                    style={{
                        flexBasis: showAttrs
                            ? `${(1 - attributesRatio) * 100}%`
                            : '100%',
                    }}
                >
                    <FilePlayer
                        file={file!}
                        autoPlay={display.playVideos}
                        controls={locked}
                        fit="contain"
                    />
                </div>
            ) : null}
            {showAttrs ? (
                <div
                    className={cn(
                        'min-w-0 overflow-y-auto p-3',
                        !locked && 'overflow-hidden'
                    )}
                    style={{
                        flexBasis: showFile
                            ? `${attributesRatio * 100}%`
                            : '100%',
                    }}
                >
                    <h3 className="mb-2 truncate text-sm font-semibold">
                        {asset.name}
                    </h3>
                    <AttributeList asset={asset} pinnedOnly dense />
                </div>
            ) : null}
            <Button
                variant={locked ? 'destructive' : 'secondary'}
                size="icon-xs"
                className="pointer-events-auto absolute top-2 right-2 shadow"
                onClick={onLockToggle}
                aria-label="Lock preview"
            >
                <LockIcon />
            </Button>
        </div>
    );
}
