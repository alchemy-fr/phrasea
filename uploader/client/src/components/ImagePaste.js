/**
 * This handler retrieves the images from the clipboard as a blob and returns it in a callback.
 *
 * @param pasteEvent
 * @param callback
 */
export function retrieveImageFromClipboardAsBlob(pasteEvent, callback) {
    if (pasteEvent.clipboardData === false) {
        return;
    }

    const {items} = pasteEvent.clipboardData;

    for (let i = 0; i < items.length; i++) {
        // Skip content if not image
        if (items[i].type.indexOf('image') === -1) {
            continue;
        }

        const blob = items[i].getAsFile();

        callback && callback(blob);
    }
}
