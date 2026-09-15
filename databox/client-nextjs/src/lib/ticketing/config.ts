import 'server-only';

/**
 * JIRA settings of the ticketing module. Server side only: the credentials
 * never reach the browser, only `ticketing.enabled` is exposed through the
 * runtime configuration.
 */
export type JiraConfig = {
    /** e.g. https://acme.atlassian.net */
    baseUrl: string;
    /** REST API version: 3 = JIRA Cloud (ADF), 2 = Server / Data Center */
    apiVersion: '2' | '3';
    projectKey: string;
    issueType: string;
    bugIssueType: string;
    labels: string[];
    /** Ready to use `Authorization` header value */
    authorization: string;
};

export class TicketingConfigError extends Error {
    constructor(message: string) {
        super(message);
        this.name = 'TicketingConfigError';
    }
}

function required(name: string): string {
    const value = process.env[name]?.trim();
    if (!value) {
        throw new TicketingConfigError(`Missing ${name}`);
    }

    return value;
}

export function getJiraConfig(): JiraConfig {
    const env = process.env;
    const baseUrl = required('DATABOX_TICKETING_JIRA_URL').replace(/\/+$/, '');
    const token = required('DATABOX_TICKETING_JIRA_API_TOKEN');
    const user = env.DATABOX_TICKETING_JIRA_USER?.trim();
    const issueType = env.DATABOX_TICKETING_JIRA_ISSUE_TYPE?.trim() || 'Task';
    const apiVersion =
        env.DATABOX_TICKETING_JIRA_API_VERSION?.trim() === '2' ? '2' : '3';

    return {
        baseUrl,
        apiVersion,
        projectKey: required('DATABOX_TICKETING_JIRA_PROJECT_KEY'),
        issueType,
        bugIssueType:
            env.DATABOX_TICKETING_JIRA_BUG_ISSUE_TYPE?.trim() || issueType,
        labels: (env.DATABOX_TICKETING_JIRA_LABELS ?? 'databox')
            .split(',')
            .map(l => l.trim())
            .filter(Boolean),
        // JIRA Cloud authenticates with `email:api-token` (Basic), while
        // Server / Data Center uses a personal access token (Bearer).
        authorization: user
            ? `Basic ${Buffer.from(`${user}:${token}`).toString('base64')}`
            : `Bearer ${token}`,
    };
}

/**
 * Keycloak endpoint used to validate the reporter's access token. Prefers the
 * internal URL, the public one is usually not resolvable from the container.
 */
export function getKeycloakUserInfoUrl(): string {
    const base = (
        process.env.KEYCLOAK_INTERNAL_URL ||
        process.env.KEYCLOAK_URL ||
        ''
    ).replace(/\/+$/, '');
    if (!base) {
        throw new TicketingConfigError('Missing KEYCLOAK_URL');
    }
    const realm = process.env.KEYCLOAK_REALM_NAME || 'phrasea';

    return `${base}/realms/${realm}/protocol/openid-connect/userinfo`;
}
