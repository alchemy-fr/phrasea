import React from "react";

export function retrieveImageFromClipboardAsBlob(
    pasteEvent: React.ClipboardEvent,
    callback: (blob: File) => void
): void {
    if (!pasteEvent.clipboardData) {
        return;
    }

    const {items} = pasteEvent.clipboardData;

    for (let i = 0; i < items.length; i++) {
        // Skip content if not image
        if (items[i].type.indexOf('image') === -1) {
            continue;
        }

        const blob = items[i].getAsFile();
        if (blob) {
            callback && callback(blob);
        }
    }
}
