import {useMatomo} from '@jonkoops/matomo-tracker-react';
import {MutableRefObject, useMemo} from 'react';

export function useTracking() {
    const {pushInstruction, trackPageView, enableLinkTracking} = useMatomo();

    return useMemo(
        () => ({
            trackPageView,
            enableLinkTracking,
            trackContentInteraction: (
                resourceId: string,
                title: string | undefined,
                interactionType: string = 'click'
            ) => {
                pushInstruction(
                    'trackContentInteraction',
                    interactionType,
                    title ?? resourceId,
                    resourceId
                );
            },
            trackContentImpression: (
                resourceId: string,
                title: string | undefined
            ) => {
                pushInstruction(
                    'trackContentImpression',
                    title ?? resourceId,
                    resourceId
                );
            },
            scanForMedia: (
                containerRef: MutableRefObject<HTMLDivElement | null>,
                interval: number = 10
            ) => {
                if (process.env.NODE_ENV !== 'production') {
                    pushInstruction('MediaAnalytics::enableDebugMode');
                }

                pushInstruction('MediaAnalytics::setPingInterval', interval);
                pushInstruction(
                    'MediaAnalytics::scanForMedia',
                    containerRef.current
                );
            },
        }),
        [pushInstruction, trackPageView, enableLinkTracking]
    );
}
