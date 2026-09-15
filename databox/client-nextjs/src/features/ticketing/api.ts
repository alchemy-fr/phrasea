'use client';

import {getAuthClient} from '@/lib/auth/client';
import type {CreateTicketPayload, CreateTicketResult} from './types';

/**
 * Posts the ticket to our own route handler, which is the only place holding
 * the JIRA credentials.
 */
export async function createTicket(
    payload: CreateTicketPayload
): Promise<CreateTicketResult> {
    const token = await getAuthClient()
        .getAccessToken()
        .catch(() => undefined);

    const response = await fetch('/api/ticketing', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            ...(token ? {Authorization: `Bearer ${token}`} : {}),
        },
        body: JSON.stringify(payload),
    });

    if (!response.ok) {
        const message = await response
            .json()
            .then((data: {message?: string}) => data?.message)
            .catch(() => undefined);

        throw new Error(message ?? `Request failed (${response.status})`);
    }

    return (await response.json()) as CreateTicketResult;
}
