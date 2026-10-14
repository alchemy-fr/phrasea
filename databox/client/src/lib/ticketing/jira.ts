import 'server-only';
import type {
    CreateTicketPayload,
    TicketKind,
    TicketSessionContext,
} from '@/features/ticketing/types';
import type {JiraConfig} from './config';
import {buildAdfDescription, buildWikiDescription} from './description';

export class JiraError extends Error {
    constructor(
        message: string,
        public readonly status: number
    ) {
        super(message);
        this.name = 'JiraError';
    }
}

export type CreatedIssue = {
    key: string;
    url: string;
};

function issueTypeFor(config: JiraConfig, kind: TicketKind): string {
    return kind === 'bug' ? config.bugIssueType : config.issueType;
}

function labelsFor(config: JiraConfig, kind: TicketKind): string[] {
    return [...new Set([...config.labels, `databox-${kind}`])];
}

async function readError(response: Response): Promise<string> {
    const body = await response.text();
    try {
        const data = JSON.parse(body) as {
            errorMessages?: string[];
            errors?: Record<string, string>;
        };
        const messages = [
            ...(data.errorMessages ?? []),
            ...Object.entries(data.errors ?? {}).map(
                ([field, message]) => `${field}: ${message}`
            ),
        ];
        if (messages.length > 0) {
            return messages.join(' — ');
        }
    } catch {
        // not JSON
    }

    return body.slice(0, 500) || response.statusText;
}

export async function createJiraIssue(
    config: JiraConfig,
    payload: Pick<CreateTicketPayload, 'kind' | 'summary' | 'description'> & {
        page: CreateTicketPayload['page'];
    },
    session: TicketSessionContext
): Promise<CreatedIssue> {
    const input = {
        description: payload.description,
        page: payload.page,
        session,
    };

    const response = await fetch(
        `${config.baseUrl}/rest/api/${config.apiVersion}/issue`,
        {
            method: 'POST',
            headers: {
                'Authorization': config.authorization,
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            cache: 'no-store',
            body: JSON.stringify({
                fields: {
                    project: {key: config.projectKey},
                    issuetype: {name: issueTypeFor(config, payload.kind)},
                    summary: payload.summary,
                    labels: labelsFor(config, payload.kind),
                    description:
                        config.apiVersion === '3'
                            ? buildAdfDescription(input)
                            : buildWikiDescription(input),
                },
            }),
        }
    );

    if (!response.ok) {
        throw new JiraError(await readError(response), response.status);
    }

    const {key} = (await response.json()) as {key: string};

    return {key, url: `${config.baseUrl}/browse/${key}`};
}

/**
 * Attaches the screenshot (a `data:` URL) to an existing issue.
 */
export async function attachScreenshot(
    config: JiraConfig,
    issueKey: string,
    dataUrl: string
): Promise<void> {
    const match = /^data:(image\/(png|jpeg|webp));base64,(.+)$/s.exec(dataUrl);
    if (!match) {
        throw new JiraError('Unsupported screenshot format', 400);
    }
    const [, mimeType, extension, base64] = match;
    const blob = new Blob([Buffer.from(base64, 'base64')], {type: mimeType});

    const form = new FormData();
    form.append(
        'file',
        blob,
        `screenshot-${Date.now()}.${extension === 'jpeg' ? 'jpg' : extension}`
    );

    const response = await fetch(
        `${config.baseUrl}/rest/api/${config.apiVersion}/issue/${encodeURIComponent(
            issueKey
        )}/attachments`,
        {
            method: 'POST',
            headers: {
                'Authorization': config.authorization,
                'Accept': 'application/json',
                // Required by JIRA's XSRF check on attachment uploads
                'X-Atlassian-Token': 'no-check',
            },
            cache: 'no-store',
            body: form,
        }
    );

    if (!response.ok) {
        throw new JiraError(await readError(response), response.status);
    }
}
