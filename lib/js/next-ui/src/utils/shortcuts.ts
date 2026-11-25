type ShortcutOptions = {
    shift?: boolean;
};

type NavigatorWithUAData = Navigator & {
    userAgentData?: {platform?: string};
};

export function isMacPlatform(): boolean {
    if (typeof navigator === 'undefined') {
        return false;
    }
    const platform =
        (navigator as NavigatorWithUAData).userAgentData?.platform ??
        navigator.platform;

    return /mac|iphone|ipad|ipod/i.test(platform ?? '');
}

/**
 * The modifier of a shortcut handled as `ctrlKey || metaKey`: "⌘" / "⇧⌘" on
 * macOS (symbols in the macOS order, without "+"), "Ctrl" / "Ctrl+Shift"
 * elsewhere.
 */
export function formatModifier(
    mac: boolean,
    {shift = false}: ShortcutOptions = {}
): string {
    if (mac) {
        return shift ? '⇧⌘' : '⌘';
    }

    return shift ? 'Ctrl+Shift' : 'Ctrl';
}

export function formatShortcut(
    key: string,
    mac: boolean,
    options: ShortcutOptions = {}
): string {
    const modifier = formatModifier(mac, options);
    const k = key.toUpperCase();

    return mac ? `${modifier}${k}` : `${modifier}+${k}`;
}
