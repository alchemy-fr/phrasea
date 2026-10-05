/**
 * Shared contracts of the ticketing module (floating "report an issue" button
 * that pushes a ticket to JIRA). Used both by the client widget and by the
 * `/api/ticketing` route handler.
 */

export const ticketKinds = ['bug', 'improvement', 'question'] as const;

export type TicketKind = (typeof ticketKinds)[number];

export type TicketClientError = {
    at: string;
    message: string;
    source?: string;
    stack?: string;
};

/**
 * Snapshot of the page the user was on when the ticket was opened.
 * Collected in the browser (see ./context.ts).
 */
export type TicketPageContext = {
    url: string;
    path: string;
    title?: string;
    referrer?: string;
    /** UI language */
    locale?: string;
    /** Metadata language (user preferences) */
    dataLocale?: string;
    theme?: string;
    timezone?: string;
    viewport?: {width: number; height: number};
    screen?: {width: number; height: number; pixelRatio: number};
    userAgent?: string;
    capturedAt: string;
    /** Last uncaught errors / rejections seen on this page */
    errors?: TicketClientError[];
};

/**
 * Session of the reporter. Never sent by the browser: it is rebuilt on the
 * server from the (verified) access token, so it cannot be forged.
 */
export type TicketSessionContext = {
    userId: string;
    username?: string;
    email?: string;
    name?: string;
    roles?: string[];
    groups?: string[];
    /** Access token expiration, ISO 8601 */
    expiresAt?: string;
    /** Session id (`sid` claim) — matches the Keycloak session */
    sessionId?: string;
    clientId?: string;
    realm?: string;
};

export type CreateTicketPayload = {
    kind: TicketKind;
    summary: string;
    description: string;
    page: TicketPageContext;
    /** Optional screenshot, as a `data:image/...;base64,` URL */
    screenshot?: string;
};

export type CreateTicketResult = {
    key: string;
    url: string;
    screenshotAttached: boolean;
};

export const summaryMaxLength = 200;
export const descriptionMaxLength = 10_000;
/** Max decoded size of an attached screenshot */
export const screenshotMaxBytes = 5 * 1024 * 1024;
