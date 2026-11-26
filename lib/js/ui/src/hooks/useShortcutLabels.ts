import {useCallback, useSyncExternalStore} from 'react';
import {useTranslation} from 'react-i18next';
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
 * macOS, "Ctrl+U" elsewhere (Shift translated).
 */
export function useShortcutLabels() {
    const {t} = useTranslation();
    const mac = useIsMac();
    const shiftLabel = t('shortcut.shift', 'Shift');

    const modifier = useCallback(
        (options?: ShortcutOptions) =>
            formatModifier(mac, {...options, shiftLabel}),
        [mac, shiftLabel]
    );
    const shortcut = useCallback(
        (key: string, options?: ShortcutOptions) =>
            formatShortcut(key, mac, {...options, shiftLabel}),
        [mac, shiftLabel]
    );

    return {mac, modifier, shortcut};
}
