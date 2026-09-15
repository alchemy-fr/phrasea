import {NextResponse} from 'next/server';
import type {CreateTicketResult} from '@/features/ticketing/types';
import {getServerConfig} from '@/lib/config/server';
import {getJiraConfig, TicketingConfigError} from '@/lib/ticketing/config';
import {
    attachScreenshot,
    createJiraIssue,
    JiraError,
} from '@/lib/ticketing/jira';
import {
    parseTicketPayload,
    TicketValidationError,
} from '@/lib/ticketing/payload';
import {consumeRateLimit} from '@/lib/ticketing/rateLimit';
import {
    getBearerToken,
    InvalidTokenError,
    resolveSession,
} from '@/lib/ticketing/session';

export const dynamic = 'force-dynamic';

function error(message: string, status: number, extra?: ResponseInit) {
    return NextResponse.json({message}, {status, ...extra});
}

/**
 * Creates a JIRA issue out of a user report, enriched with the page they were
 * on and their (server side verified) session.
 */
export async function POST(request: Request): Promise<Response> {
    if (!getServerConfig().ticketing.enabled) {
        return error('Ticketing is disabled', 404);
    }

    let jira;
    try {
        jira = getJiraConfig();
    } catch (e) {
        if (e instanceof TicketingConfigError) {
            console.error('[ticketing] misconfigured:', e.message);

            return error('Ticketing is not configured', 503);
        }
        throw e;
    }

    const token = getBearerToken(request);
    if (!token) {
        return error('Authentication required', 401);
    }

    let session;
    try {
        session = await resolveSession(token);
    } catch (e) {
        if (e instanceof InvalidTokenError) {
            return error('Authentication required', 401);
        }
        console.error('[ticketing] unable to verify the session:', e);

        return error('Unable to verify the session', 502);
    }

    let payload;
    try {
        payload = parseTicketPayload(await request.json());
    } catch (e) {
        return error(
            e instanceof TicketValidationError ? e.message : 'Invalid payload',
            400
        );
    }

    const {allowed, retryAfter} = consumeRateLimit(session.userId);
    if (!allowed) {
        return error('Too many tickets created, please retry later', 429, {
            headers: {'Retry-After': String(retryAfter)},
        });
    }

    let issue;
    try {
        issue = await createJiraIssue(jira, payload, session);
    } catch (e) {
        console.error('[ticketing] issue creation failed:', e);

        return error(
            e instanceof JiraError
                ? `JIRA rejected the ticket: ${e.message}`
                : 'Unable to create the ticket',
            502
        );
    }

    // The ticket exists: a failed screenshot upload must not fail the request.
    let screenshotAttached = false;
    if (payload.screenshot) {
        try {
            await attachScreenshot(jira, issue.key, payload.screenshot);
            screenshotAttached = true;
        } catch (e) {
            console.error('[ticketing] screenshot upload failed:', e);
        }
    }

    return NextResponse.json({
        ...issue,
        screenshotAttached,
    } satisfies CreateTicketResult);
}
