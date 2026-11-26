'use client';

import {useTranslation} from 'react-i18next';
import {CookieIcon} from 'lucide-react';
import {DropdownMenuItem} from '@alchemy/ui/ui/menu';
import type {MatomoConfig} from './matomo';
import {openCookieConsentBanner} from './cookieConsent';

/**
 * The settings menu item reopening the cookie consent banner, for the visitor
 * to change the choice. Nothing when the visitor is not asked (no Matomo,
 * consent not required, cookies disabled).
 */
export function CookieConsentMenuItem({
    matomo,
}: {
    matomo: MatomoConfig | undefined;
}) {
    const {t} = useTranslation();

    if (!matomo?.cookieConsent || matomo.disableCookies) {
        return null;
    }

    return (
        <DropdownMenuItem
            data-testid="cookie-consent-menu"
            onSelect={() => openCookieConsentBanner()}
        >
            <CookieIcon /> {t('analytics.cookie_consent.menu', 'Cookies')}
        </DropdownMenuItem>
    );
}
