/** A cue of a WebVTT file */
export type WebVttCue = {
    /** Seconds */
    start: number;
    end: number;
    /** Plain text, lines joined by '\n'; `[hl]` markers kept */
    text: string;
    /** Speaker of a `<v Name>` span, if any */
    voice?: string;
};

const timestamp = String.raw`(?:(\d{2,}):)?(\d{2}):(\d{2})\.(\d{3})`;
const timingLine = new RegExp(
    String.raw`^\s*${timestamp}\s+-->\s+${timestamp}(?:\s+.*)?$`
);

function seconds(
    h: string | undefined,
    m: string,
    s: string,
    ms: string
): number {
    return (
        (h ? parseInt(h, 10) * 3600 : 0) +
        parseInt(m, 10) * 60 +
        parseInt(s, 10) +
        parseInt(ms, 10) / 1000
    );
}

function stripHighlightMarkers(line: string): string {
    return line.replace(/\[\/?hl\]/g, '');
}

const entities: Record<string, string> = {
    amp: '&',
    lt: '<',
    gt: '>',
    nbsp: '\u00a0',
    lrm: '\u200e',
    rlm: '\u200f',
    quot: '"',
    apos: "'",
};

function decodeEntities(text: string): string {
    return text.replace(
        /&(#x[0-9a-f]+|#\d+|[a-z]+);/gi,
        (match, name: string) => {
            if (name[0] === '#') {
                const code =
                    name[1] === 'x' || name[1] === 'X'
                        ? parseInt(name.slice(2), 16)
                        : parseInt(name.slice(1), 10);
                try {
                    return String.fromCodePoint(code);
                } catch {
                    return match;
                }
            }

            return entities[name.toLowerCase()] ?? match;
        }
    );
}

/** The text of a cue without its tags, and the speaker of its first voice */
function cueText(lines: string[]): {text: string; voice?: string} {
    const raw = lines.join('\n');
    const voice = raw.match(/<v(?:\.[^\s>]*)?\s+([^>]+)>/)?.[1]?.trim();
    // Tags (`<v Name>`, `<c.x>`, `<i>`, `<00:00:01.000>`…) are dropped, not
    // the `[hl]` markers of a search
    const text = decodeEntities(raw.replace(/<[^>]*>/g, ''))
        .split('\n')
        .map(l => l.trim())
        .join('\n')
        .trim();

    return {
        text,
        voice: voice ? stripHighlightMarkers(decodeEntities(voice)) : undefined,
    };
}

/**
 * The cues of a WebVTT file, in the order of the file. A source without the
 * `WEBVTT` header has none. The `[hl]` markers of a search highlight are
 * ignored on the identifier and timing lines, kept in the text.
 */
export function parseWebVtt(source: string): WebVttCue[] {
    const normalized = source.replace(/^\uFEFF/, '').replace(/\r\n?/g, '\n');
    const blocks = normalized.split(/\n[ \t]*\n+/);
    const header = stripHighlightMarkers(blocks[0] ?? '');
    if (!/^WEBVTT(?:[ \t].*)?(?:\n|$)/.test(header)) {
        return [];
    }

    const cues: WebVttCue[] = [];
    // The header block may hold more lines (metadata): no cue there
    for (const block of blocks.slice(1)) {
        const lines = block.split('\n');
        while (lines.length && lines[0].trim() === '') {
            lines.shift();
        }
        if (!lines.length) {
            continue;
        }
        const first = stripHighlightMarkers(lines[0]).trim();
        if (/^(NOTE|STYLE|REGION)(?:\s|$)/.test(first)) {
            continue;
        }
        let timingIndex = 0;
        let timing = first.match(timingLine);
        if (!timing && lines.length > 1) {
            // The first line is the identifier of the cue
            timingIndex = 1;
            timing = stripHighlightMarkers(lines[1]).match(timingLine);
        }
        if (!timing) {
            continue;
        }
        const [, h1, m1, s1, ms1, h2, m2, s2, ms2] = timing;
        const {text, voice} = cueText(lines.slice(timingIndex + 1));
        if (!text) {
            continue;
        }
        cues.push({
            start: seconds(h1, m1, s1, ms1),
            end: seconds(h2, m2, s2, ms2),
            text,
            ...(voice ? {voice} : {}),
        });
    }

    return cues;
}

/** The last cue on screen at the time, `-1` for none */
export function activeCueIndex(cues: WebVttCue[], time: number): number {
    for (let i = cues.length - 1; i >= 0; i--) {
        if (cues[i].start <= time && time < cues[i].end) {
            return i;
        }
    }

    return -1;
}
