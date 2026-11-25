import {useCallback, useSyncExternalStore} from 'react';
import {
    formatModifier,
    formatShortcut,
    isMacPlatform,
} from '../utils/shortcuts';

const subscribeNoop = () => () => {};

/**
 * Whether the browser runs on macOS / iOS. The server render and the
 * hydration see "not Mac", then the client renders again with the actual
 * platform, so that the markup never mismatches.
 */
export function useIsMac(): boolean {
    return useSyncExternalStore(subscribeNoop, isMacPlatform, () => false);
}

type ShortcutOptions = {shift?: boolean};

/**
 * Labels of the keyboard shortcuts handled as `ctrlKey || metaKey`: "⌘U" on
 * macOS, "Ctrl+U" elsewhere.
 */
export function useShortcutLabels() {
    const mac = useIsMac();

    const modifier = useCallback(
        (options?: ShortcutOptions) => formatModifier(mac, options),
        [mac]
    );
    const shortcut = useCallback(
        (key: string, options?: ShortcutOptions) =>
            formatShortcut(key, mac, options),
        [mac]
    );

    return {mac, modifier, shortcut};
}
