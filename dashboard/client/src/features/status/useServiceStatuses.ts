'use client';

import {useQuery} from '@tanstack/react-query';
import type {ServiceStatuses} from './types';

export const STATUS_REFRESH_INTERVAL_MS = 60_000;

async function fetchStatuses(): Promise<ServiceStatuses> {
    const res = await fetch('/api/status', {cache: 'no-store'});
    if (!res.ok) {
        throw new Error(`HTTP ${res.status}`);
    }

    return res.json();
}

/** The status of the APIs, refreshed every minute while the page is visible */
export function useServiceStatuses() {
    return useQuery({
        queryKey: ['service-statuses'],
        queryFn: fetchStatuses,
        refetchInterval: STATUS_REFRESH_INTERVAL_MS,
        staleTime: STATUS_REFRESH_INTERVAL_MS / 2,
    });
}
