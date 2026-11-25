'use client';

import {useSyncExternalStore} from 'react';
import {useTranslation} from 'react-i18next';
import {Button} from '@alchemy/next-ui/ui/button';
import {isCookieConsentRequired} from './matomo';
import {chooseCookieConsent, getSnapshot, subscribe} from './cookieConsent';

function isInFrame(): boolean {
    try {
        return window.self !== window.top;
    } catch {
        return true;
    }
}

function isBannerVisible(): boolean {
    if (!isCookieConsentRequired() || isInFrame()) {
        return false;
    }
    const {consent, bannerRequested} = getSnapshot();

    return !consent || bannerRequested;
}

/**
 * Asks the visitor whether Matomo may set its cookies, while no valid choice
 * is stored or when the settings menu reopens it. Not shown in a frame (embed
 * on a third party site): Matomo tracks there without cookies, unless the
 * visitor accepted them on this origin.
 *
 * Accepting and refusing weigh the same, and the banner cannot be closed
 * without choosing. It does not block the page.
 *
 * Only shown once mounted: the server knows nothing of the stored choice.
 */
export function CookieConsentBanner() {
    const {t} = useTranslation();
    const visible = useSyncExternalStore(
        subscribe,
        isBannerVisible,
        () => false
    );

    if (!visible) {
        return null;
    }

    return (
        <div
            role="region"
            aria-label={t('analytics.cookie_consent.region', 'Cookie consent')}
            data-testid="cookie-consent-banner"
            className="fixed inset-x-0 bottom-0 z-50 border-t bg-popover p-4 text-popover-foreground shadow-lg sm:inset-x-auto sm:bottom-4 sm:left-4 sm:max-w-sm sm:rounded-lg sm:border"
        >
            <p className="text-sm font-semibold">
                {t('analytics.cookie_consent.title', 'Audience measurement')}
            </p>
            <p className="mt-1 text-sm text-muted-foreground">
                {t(
                    'analytics.cookie_consent.message',
                    'We measure the audience of this site with Matomo. Do you accept that it stores cookies in your browser? Without them, the audience is still measured anonymously, without cookies.'
                )}
            </p>
            <div className="mt-3 flex justify-end gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    data-testid="cookie-consent-refuse"
                    onClick={() => chooseCookieConsent(false)}
                >
                    {t('analytics.cookie_consent.refuse', 'Refuse')}
                </Button>
                <Button
                    variant="outline"
                    size="sm"
                    data-testid="cookie-consent-accept"
                    onClick={() => chooseCookieConsent(true)}
                >
                    {t('analytics.cookie_consent.accept', 'Accept')}
                </Button>
            </div>
        </div>
    );
}
