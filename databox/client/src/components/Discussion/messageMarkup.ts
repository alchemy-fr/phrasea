/**
 * Discussion message format, shared with the Next.js client
 * (`databox/client-nextjs`) and the API:
 *
 * - the content is plain text, stored as typed;
 * - a user mention is `@[username](userId)` (the `react-mentions` markup of
 *   the legacy client, parsed by the API `MentionExtractor` to subscribe the
 *   mentioned users to the thread);
 * - a small markdown subset formats it: `**bold**`, `_italic_` / `*italic*`,
 *   `~~strike~~`, `` `code` ``, `[label](https://…)`, bare URLs, ``` fenced
 *   code blocks, `- ` / `1. ` lists and `> ` quotes. Anything else stays
 *   plain text, so a message is still readable as raw text.
 *
 * Parsing half of `databox/client-nextjs/src/features/discussion/messageMarkup.ts`:
 * keep both in sync.
 */

export type InlineNode =
    | {type: 'text'; text: string}
    | {type: 'mention'; username: string; id: string}
    | {type: 'link'; href: string; label: string}
    | {type: 'code'; text: string}
    | {type: 'strong' | 'em' | 'del'; children: InlineNode[]}
    | {type: 'br'};

export type BlockNode =
    | {type: 'paragraph'; children: InlineNode[]}
    | {type: 'list'; ordered: boolean; start: number; items: InlineNode[][]}
    | {type: 'quote'; children: InlineNode[]}
    | {type: 'codeblock'; text: string};

const inlineRegex = new RegExp(
    [
        '`(?<code>[^`\\n]+)`',
        '@\\[(?<mentionName>[^\\]\\n]+)\\]\\((?<mentionId>[^)\\s]+)\\)',
        '\\[(?<linkLabel>[^\\]\\n]+)\\]\\((?<linkHref>https?:\\/\\/[^)\\s]+)\\)',
        '(?<url>https?:\\/\\/[^\\s<]+)',
        '\\*\\*(?<strong>\\S(?:[^\\n]*?\\S)?)\\*\\*',
        '~~(?<del>\\S(?:[^\\n]*?\\S)?)~~',
        '(?<![\\w*])\\*(?<em1>[^\\s*](?:[^*\\n]*?[^\\s*])?)\\*(?![\\w*])',
        '(?<!\\w)_(?<em2>[^\\s_](?:[^_\\n]*?[^\\s_])?)_(?!\\w)',
    ].join('|'),
    'g'
);

/** Trailing punctuation is not part of a bare URL ("see https://x.com."). */
function splitUrl(raw: string): [string, string] {
    let url = raw;
    for (;;) {
        const last = url[url.length - 1];
        if (/[.,;:!?'"]/.test(last)) {
            url = url.slice(0, -1);
        } else if (
            last === ')' &&
            url.split('(').length < url.split(')').length
        ) {
            url = url.slice(0, -1);
        } else {
            break;
        }
    }

    return [url, raw.slice(url.length)];
}

function pushText(nodes: InlineNode[], text: string) {
    if (!text) {
        return;
    }
    const last = nodes[nodes.length - 1];
    if (last?.type === 'text') {
        last.text += text;
    } else {
        nodes.push({type: 'text', text});
    }
}

/** Parses one line of text. */
export function parseInline(text: string): InlineNode[] {
    const nodes: InlineNode[] = [];
    const re = new RegExp(inlineRegex.source, 'g');
    let pos = 0;
    let m: RegExpExecArray | null;
    while ((m = re.exec(text))) {
        pushText(nodes, text.slice(pos, m.index));
        pos = m.index + m[0].length;
        const g = m.groups!;
        if (g.code !== undefined) {
            nodes.push({type: 'code', text: g.code});
        } else if (g.mentionName !== undefined) {
            nodes.push({
                type: 'mention',
                username: g.mentionName,
                id: g.mentionId,
            });
        } else if (g.linkLabel !== undefined) {
            nodes.push({type: 'link', href: g.linkHref, label: g.linkLabel});
        } else if (g.url !== undefined) {
            const [url, rest] = splitUrl(g.url);
            nodes.push({type: 'link', href: url, label: url});
            // The trailing punctuation may open the next token
            pos -= rest.length;
            re.lastIndex = pos;
        } else if (g.strong !== undefined) {
            nodes.push({type: 'strong', children: parseInline(g.strong)});
        } else if (g.del !== undefined) {
            nodes.push({type: 'del', children: parseInline(g.del)});
        } else {
            nodes.push({
                type: 'em',
                children: parseInline(g.em1 ?? g.em2),
            });
        }
    }
    pushText(nodes, text.slice(pos));

    return nodes;
}

function parseLines(lines: string[]): InlineNode[] {
    const nodes: InlineNode[] = [];
    lines.forEach((line, i) => {
        if (i > 0) {
            nodes.push({type: 'br'});
        }
        nodes.push(...parseInline(line));
    });

    return nodes;
}

const bulletRegex = /^\s*[-*+]\s+(.*)$/;
const orderedRegex = /^\s*(\d{1,9})[.)]\s+(.*)$/;
const quoteRegex = /^>\s?(.*)$/;
const fenceRegex = /^\s*```/;

/** Parses a whole message into blocks. */
export function parseMessage(content: string | null | undefined): BlockNode[] {
    const lines = (content ?? '').replace(/\r\n?/g, '\n').split('\n');
    const blocks: BlockNode[] = [];
    let paragraph: string[] = [];

    const flush = () => {
        if (paragraph.length > 0) {
            blocks.push({type: 'paragraph', children: parseLines(paragraph)});
            paragraph = [];
        }
    };

    let i = 0;
    while (i < lines.length) {
        const line = lines[i];
        if (fenceRegex.test(line)) {
            const end = lines.findIndex(
                (l, j) => j > i && /^\s*```\s*$/.test(l)
            );
            // An unclosed fence is plain text
            if (end !== -1) {
                flush();
                blocks.push({
                    type: 'codeblock',
                    text: lines.slice(i + 1, end).join('\n'),
                });
                i = end + 1;
                continue;
            }
        }

        const bullet = bulletRegex.test(line);
        const ordered = orderedRegex.exec(line);
        if (bullet || ordered) {
            flush();
            const regex = bullet ? bulletRegex : orderedRegex;
            const items: InlineNode[][] = [];
            while (i < lines.length && regex.test(lines[i])) {
                const m = regex.exec(lines[i])!;
                items.push(parseInline(bullet ? m[1] : m[2]));
                i++;
            }
            blocks.push({
                type: 'list',
                ordered: !bullet,
                start: ordered ? parseInt(ordered[1], 10) : 1,
                items,
            });
            continue;
        }

        if (quoteRegex.test(line)) {
            flush();
            const quoted: string[] = [];
            while (i < lines.length && quoteRegex.test(lines[i])) {
                quoted.push(quoteRegex.exec(lines[i])![1]);
                i++;
            }
            blocks.push({type: 'quote', children: parseLines(quoted)});
            continue;
        }

        if (line.trim() === '') {
            flush();
        } else {
            paragraph.push(line);
        }
        i++;
    }
    flush();

    return blocks;
}
