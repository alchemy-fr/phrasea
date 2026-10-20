import {useEffect, useState} from 'react';

type ParadeStatus = {
    enabled: boolean;
    url?: string;
    canAdminister: boolean;
};

export function useParadeStatus(
    databoxApiUrl: string | undefined,
    accessToken: string | undefined,
    fallback: Omit<ParadeStatus, 'canAdminister'>
): ParadeStatus {
    const [status, setStatus] = useState<ParadeStatus>({
        ...fallback,
        canAdminister: false,
    });

    useEffect(() => {
        if (!databoxApiUrl) {
            return;
        }
        const controller = new AbortController();
        fetch(`${databoxApiUrl}/parade-status`, {
            signal: controller.signal,
            headers: accessToken ? {Authorization: `Bearer ${accessToken}`} : {},
        })
            .then(response => (response.ok ? response.json() : null))
            .then(
                (
                    data: {
                        enabled?: unknown;
                        url?: unknown;
                        canAdminister?: unknown;
                    } | null
                ) => {
                    if (data) {
                        setStatus({
                            enabled: data.enabled === true,
                            url: typeof data.url === 'string' ? data.url : undefined,
                            canAdminister: data.canAdminister === true,
                        });
                    }
                }
            )
            .catch(() => {});

        return () => controller.abort();
    }, [databoxApiUrl, accessToken]);

    return status;
}
