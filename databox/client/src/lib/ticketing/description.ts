/**
 * Renders the ticket body sent to JIRA. Pure functions (no env, no I/O) so
 * they can be unit tested.
 *
 * JIRA Cloud (REST API v3) expects the Atlassian Document Format, while
 * JIRA Server / Data Center (v2) expects wiki markup: both are built here.
 */
import type {
    CreateTicketPayload,
    TicketPageContext,
    TicketSessionContext,
} from '@/features/ticketing/types';

export type AdfNode = {
    type: string;
    text?: string;
    content?: AdfNode[];
    attrs?: Record<string, unknown>;
    marks?: {type: string; attrs?: Record<string, unknown>}[];
};

export type AdfDoc = {
    type: 'doc';
    version: 1;
    content: AdfNode[];
};

type Line = [label: string, value: string | undefined];

function lines(entries: Line[]): string {
    const width = Math.max(
        ...entries.filter(([, v]) => v).map(([label]) => label.length),
        0
    );

    return entries
        .filter((entry): entry is [string, string] => !!entry[1])
        .map(([label, value]) => `${label.padEnd(width)} : ${value}`)
        .join('\n');
}

export function pageLines(page: TicketPageContext): string {
    return lines([
        ['URL', page.url],
        ['Path', page.path],
        ['Title', page.title],
        ['Referrer', page.referrer],
        ['Captured at', page.capturedAt],
        ['UI locale', page.locale],
        ['Data locale', page.dataLocale],
        ['Theme', page.theme],
        ['Timezone', page.timezone],
        [
            'Viewport',
            page.viewport
                ? `${page.viewport.width}x${page.viewport.height}`
                : undefined,
        ],
        [
            'Screen',
            page.screen
                ? `${page.screen.width}x${page.screen.height} @${page.screen.pixelRatio}x`
                : undefined,
        ],
        ['User agent', page.userAgent],
    ]);
}

export function sessionLines(session: TicketSessionContext): string {
    return lines([
        ['User', session.name ?? session.username],
        ['Username', session.username],
        ['Email', session.email],
        ['User ID', session.userId],
        ['Session ID', session.sessionId],
        ['Roles', session.roles?.join(', ')],
        ['Groups', session.groups?.join(', ')],
        ['Realm', session.realm],
        ['Client ID', session.clientId],
        ['Token expires at', session.expiresAt],
    ]);
}

export function errorLines(page: TicketPageContext): string | undefined {
    if (!page.errors?.length) {
        return undefined;
    }

    return page.errors
        .map(e =>
            [
                `[${e.at}] ${e.message}`,
                e.source ? `  at ${e.source}` : undefined,
                e.stack,
            ]
                .filter(Boolean)
                .join('\n')
        )
        .join('\n\n');
}

function paragraph(text: string): AdfNode {
    return {
        type: 'paragraph',
        content: text ? [{type: 'text', text}] : [],
    };
}

function heading(text: string): AdfNode {
    return {
        type: 'heading',
        attrs: {level: 3},
        content: [{type: 'text', text}],
    };
}

function codeBlock(text: string): AdfNode {
    return {
        type: 'codeBlock',
        attrs: {},
        content: [{type: 'text', text}],
    };
}

function link(url: string): AdfNode {
    return {
        type: 'paragraph',
        content: [
            {
                type: 'text',
                text: url,
                marks: [{type: 'link', attrs: {href: url}}],
            },
        ],
    };
}

export type DescriptionInput = Pick<
    CreateTicketPayload,
    'description' | 'page'
> & {
    session: TicketSessionContext;
};

/** JIRA Cloud (REST v3) */
export function buildAdfDescription({
    description,
    page,
    session,
}: DescriptionInput): AdfDoc {
    const errors = errorLines(page);

    return {
        type: 'doc',
        version: 1,
        content: [
            ...description.split(/\n{2,}/).map(paragraph),
            {type: 'rule'},
            heading('Page'),
            link(page.url),
            codeBlock(pageLines(page)),
            heading('Session'),
            codeBlock(sessionLines(session)),
            ...(errors ? [heading('Console errors'), codeBlock(errors)] : []),
        ],
    };
}

/** JIRA Server / Data Center (REST v2, wiki markup) */
export function buildWikiDescription({
    description,
    page,
    session,
}: DescriptionInput): string {
    const errors = errorLines(page);

    return [
        description,
        '----',
        'h3. Page',
        page.url,
        `{code}\n${pageLines(page)}\n{code}`,
        'h3. Session',
        `{code}\n${sessionLines(session)}\n{code}`,
        ...(errors ? ['h3. Console errors', `{code}\n${errors}\n{code}`] : []),
    ].join('\n\n');
}
