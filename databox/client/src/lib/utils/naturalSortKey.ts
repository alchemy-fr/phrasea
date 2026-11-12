/** In code points, as App\Util\NaturalSortKey::MAX_LENGTH */
const MAX_LENGTH = 1000;

/**
 * Sort key giving a natural, case and accent insensitive order ("Lot 2"
 * before "Lot 10"), to compare with `compareCodePoints`.
 *
 * Mirrors App\Util\NaturalSortKey::fromString() (databox API), which sorts
 * the collections server side: both must give the same key.
 */
export function naturalSortKey(s: string | null | undefined): string {
    if (!s) {
        return '';
    }

    const key = s
        .normalize('NFD')
        .toLowerCase()
        .replace(/\p{Mn}+/gu, '')
        .replace(/[\p{Z}\u0009-\u000D]+/gu, ' ')
        .replace(/^ +| +$/g, '')
        .replace(/[0-9]+/g, d => {
            const t = d.replace(/^0+/, '') || '0';

            return String(Math.min(t.length, 99)).padStart(2, '0') + t;
        });

    return Array.from(key).slice(0, MAX_LENGTH).join('');
}

/**
 * Code point order (the one of strcmp() on UTF-8 and of Elasticsearch),
 * which differs from the UTF-16 code unit order of `<` for the characters
 * outside the BMP.
 */
export function compareCodePoints(a: string, b: string): number {
    const length = Math.min(a.length, b.length);
    for (let i = 0; i < length; i++) {
        let ca = a.charCodeAt(i);
        let cb = b.charCodeAt(i);
        if (ca !== cb) {
            if (ca >= 0xd800 && cb >= 0xd800) {
                // Surrogates (astral code points) go after U+E000-U+FFFF
                ca += ca >= 0xe000 ? -0x800 : 0x2000;
                cb += cb >= 0xe000 ? -0x800 : 0x2000;
            }

            return ca < cb ? -1 : 1;
        }
    }

    return a.length === b.length ? 0 : a.length < b.length ? -1 : 1;
}
