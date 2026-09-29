import type {JSONContent} from '@tiptap/core';
import {BlockNode, InlineNode, parseMessage} from './messageMarkup';

/**
 * Conversions between the stored message format (`messageMarkup`) and the
 * document edited by the WYSIWYG composer (tiptap JSON, see
 * `editorExtensions`). The stored format does not change: plain text, a
 * markdown subset and `@[username](userId)` mentions.
 */

type Mark = NonNullable<JSONContent['marks']>[number];

// ---------------------------------------------------------------------------
// markup => document

function inlineToDoc(nodes: InlineNode[], marks: Mark[] = []): JSONContent[] {
    const withMarks = (n: JSONContent, extra: Mark[] = []): JSONContent => {
        const all = [...marks, ...extra];

        return all.length > 0 ? {...n, marks: all} : n;
    };

    return nodes.flatMap((n): JSONContent[] => {
        switch (n.type) {
            case 'text':
                return n.text ? [withMarks({type: 'text', text: n.text})] : [];
            case 'br':
                return [{type: 'hardBreak'}];
            case 'mention':
                return [
                    withMarks({
                        type: 'mention',
                        attrs: {id: n.id, username: n.username},
                    }),
                ];
            case 'code':
                // The code mark excludes the others
                return [{type: 'text', text: n.text, marks: [{type: 'code'}]}];
            case 'link':
                return [
                    withMarks({type: 'text', text: n.label}, [
                        {type: 'link', attrs: {href: n.href}},
                    ]),
                ];
            case 'strong':
            case 'em':
            case 'del':
                return inlineToDoc(n.children, [
                    ...marks,
                    {
                        type:
                            n.type === 'strong'
                                ? 'bold'
                                : n.type === 'em'
                                  ? 'italic'
                                  : 'strike',
                    },
                ]);
        }
    });
}

function paragraph(nodes: JSONContent[]): JSONContent {
    return nodes.length > 0
        ? {type: 'paragraph', content: nodes}
        : {type: 'paragraph'};
}

/** Splits inline nodes on line breaks (one paragraph per quoted line). */
function splitLines(nodes: InlineNode[]): InlineNode[][] {
    const lines: InlineNode[][] = [[]];
    for (const n of nodes) {
        if (n.type === 'br') {
            lines.push([]);
        } else {
            lines[lines.length - 1].push(n);
        }
    }

    return lines;
}

function blockToDoc(block: BlockNode): JSONContent {
    switch (block.type) {
        case 'paragraph':
            return paragraph(inlineToDoc(block.children));
        case 'quote':
            return {
                type: 'blockquote',
                content: splitLines(block.children).map(l =>
                    paragraph(inlineToDoc(l))
                ),
            };
        case 'codeblock':
            return block.text
                ? {
                      type: 'codeBlock',
                      content: [{type: 'text', text: block.text}],
                  }
                : {type: 'codeBlock'};
        case 'list':
            return {
                type: block.ordered ? 'orderedList' : 'bulletList',
                ...(block.ordered ? {attrs: {start: block.start}} : {}),
                content: block.items.map(item => ({
                    type: 'listItem',
                    content: [paragraph(inlineToDoc(item))],
                })),
            };
    }
}

export function markupToDoc(content: string | null | undefined): JSONContent {
    const blocks = parseMessage(content).map(blockToDoc);

    return {
        type: 'doc',
        content: blocks.length > 0 ? blocks : [{type: 'paragraph'}],
    };
}

/** The message quoted: every line prefixed with `>`. */
export function quoteMarkup(content: string): string {
    return content
        .replace(/\r\n?/g, '\n')
        .trim()
        .split('\n')
        .map(l => (l ? `> ${l}` : '>'))
        .join('\n');
}

// ---------------------------------------------------------------------------
// document => markup

const markers: Record<string, string> = {
    bold: '**',
    italic: '_',
    strike: '~~',
    code: '`',
};
const markOrder = ['bold', 'italic', 'strike', 'code'];

type Token = {
    /** Serialized content */
    text: string;
    /** Plain text, that surrounding whitespace can be moved out of markers */
    plain: boolean;
    marks: string[];
};

function isHttp(href: unknown): href is string {
    return typeof href === 'string' && /^https?:\/\/\S+$/i.test(href);
}

function tokenize(nodes: JSONContent[], hardBreak: string): Token[] {
    const tokens: Token[] = [];
    let link: {href: string; text: string; marks: string[]} | undefined;
    const flushLink = () => {
        if (link) {
            tokens.push({
                text:
                    link.text === link.href
                        ? link.href
                        : `[${link.text}](${link.href})`,
                plain: false,
                marks: link.marks,
            });
            link = undefined;
        }
    };

    for (const n of nodes) {
        const nodeMarks = n.marks ?? [];
        const href = nodeMarks.find(m => m.type === 'link')?.attrs?.href;
        let marks = markOrder.filter(t => nodeMarks.some(m => m.type === t));

        if (n.type === 'text' && isHttp(href) && n.text) {
            if (link && link.href === href) {
                link.text += n.text;
                // A link keeps the marks common to all of its parts
                link.marks = link.marks.filter(m => marks.includes(m));
            } else {
                flushLink();
                link = {href, text: n.text, marks};
            }
            continue;
        }
        flushLink();

        if (n.type === 'text') {
            const text = n.text ?? '';
            // Formatted blanks cannot be expressed ("** **")
            if (!text.trim()) {
                marks = [];
            }
            tokens.push({
                text,
                plain: !marks.includes('code'),
                marks,
            });
        } else if (n.type === 'mention') {
            tokens.push({
                text: `@[${n.attrs?.username}](${n.attrs?.id})`,
                plain: false,
                marks,
            });
        } else if (n.type === 'hardBreak') {
            tokens.push({text: hardBreak, plain: true, marks: []});
        } else if (n.content) {
            tokens.push(...tokenize(n.content, hardBreak));
        }
    }
    flushLink();

    return tokens;
}

function inlineToMarkup(
    nodes: JSONContent[] | undefined,
    hardBreak = '\n'
): string {
    let out = '';
    const active: string[] = [];

    const close = (keep: number) => {
        while (active.length > keep) {
            const marker = markers[active.pop()!];
            // Trailing blanks go after the closing marker
            const trail = /[ \t]*$/.exec(out)![0];
            out = out.slice(0, out.length - trail.length) + marker + trail;
        }
    };

    for (const token of tokenize(nodes ?? [], hardBreak)) {
        let common = 0;
        while (
            common < active.length &&
            common < token.marks.length &&
            active[common] === token.marks[common]
        ) {
            common++;
        }
        close(common);

        let text = token.text;
        const opening = token.marks.slice(common);
        if (opening.length > 0 && token.plain) {
            // Leading blanks go before the opening marker
            const lead = /^[ \t]*/.exec(text)![0];
            out += lead;
            text = text.slice(lead.length);
        }
        for (const m of opening) {
            out += markers[m];
            active.push(m);
        }
        out += text;
    }
    close(0);

    return out;
}

function listToLines(list: JSONContent): string[] {
    const ordered = list.type === 'orderedList';
    let n = ordered ? Number(list.attrs?.start ?? 1) || 1 : 1;
    const lines: string[] = [];

    for (const item of list.content ?? []) {
        let first = true;
        for (const child of item.content ?? []) {
            if (child.type === 'bulletList' || child.type === 'orderedList') {
                // Nested lists are flattened: the format has one level
                lines.push(...listToLines(child));
            } else {
                const text = blockText(child, ' ');
                if (first) {
                    lines.push(`${ordered ? `${n++}.` : '-'} ${text}`);
                    first = false;
                } else if (text) {
                    lines[lines.length - 1] += ` ${text}`;
                }
            }
        }
        if (first) {
            lines.push(ordered ? `${n++}. ` : '- ');
        }
    }

    return lines;
}

function blockText(node: JSONContent, hardBreak = '\n'): string {
    switch (node.type) {
        case 'paragraph':
        case 'heading':
            return inlineToMarkup(node.content, hardBreak);
        case 'codeBlock':
            return (node.content ?? []).map(n => n.text ?? '').join('');
        case 'bulletList':
        case 'orderedList':
            return listToLines(node).join(hardBreak);
        default:
            return (node.content ?? [])
                .map(n => blockText(n, hardBreak))
                .join(hardBreak);
    }
}

function blockToMarkup(node: JSONContent): string | undefined {
    switch (node.type) {
        case 'paragraph': {
            const text = inlineToMarkup(node.content);

            // Empty paragraphs (the trailing one, …) carry nothing
            return text.trim() ? text : undefined;
        }
        case 'blockquote': {
            const lines = (node.content ?? []).flatMap(
                child => blockToMarkup(child)?.split('\n') ?? ['']
            );
            // Drop the leading and trailing blank lines of the quote
            while (lines.length > 0 && !lines[0].trim()) {
                lines.shift();
            }
            while (lines.length > 0 && !lines[lines.length - 1].trim()) {
                lines.pop();
            }

            return lines.length > 0
                ? lines.map(l => (l ? `> ${l}` : '>')).join('\n')
                : undefined;
        }
        case 'codeBlock':
            return `\`\`\`\n${blockText(node)}\n\`\`\``;
        case 'bulletList':
        case 'orderedList':
            return listToLines(node).join('\n');
        default: {
            const text = blockText(node);

            return text.trim() ? text : undefined;
        }
    }
}

/** Composer document => stored content */
export function docToMarkup(doc: JSONContent): string {
    let out = '';
    let previous: string | undefined;
    for (const node of doc.content ?? []) {
        const text = blockToMarkup(node);
        if (text === undefined) {
            continue;
        }
        if (previous !== undefined) {
            // Consecutive paragraphs are told apart by a blank line
            out +=
                previous === 'paragraph' && node.type === 'paragraph'
                    ? '\n\n'
                    : '\n';
        }
        out += text;
        previous = node.type;
    }

    return out.trim();
}
