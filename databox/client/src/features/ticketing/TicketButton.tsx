'use client';

import {useEffect, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {useTheme} from 'next-themes';
import {LifeBuoyIcon} from 'lucide-react';
import {Button} from '@/components/ui/button';
import {Tooltip} from '@/components/ui/overlays';
import {KEEP_MODAL_OPEN_ATTR} from '@/components/ui/dialog';
import {cn} from '@/lib/utils/cn';
import {useConfig} from '@/lib/config/ConfigProvider';
import {useAuth} from '@/lib/auth/AuthProvider';
import {usePreferencesStore} from '@/features/preferences/store';
import {collectPageContext, installClientErrorCollector} from './context';
import {TicketDialog} from './TicketDialog';
import type {TicketPageContext} from './types';

/**
 * Floating button that opens a ticket in JIRA. Rendered only when
 * `DATABOX_TICKETING_ENABLED` is on and a user is signed in.
 *
 * It stays clickable above any open modal, so an issue can be reported from
 * a dialog without closing it. It is hidden while its own dialog is open, so
 * that it is not on the page capture either.
 */
export function TicketButton() {
    const {t, i18n} = useTranslation();
    const {ticketing} = useConfig();
    const {isAuthenticated} = useAuth();
    const {resolvedTheme} = useTheme();
    const dataLocale = usePreferencesStore(s => s.preferences.dataLocale);
    const [open, setOpen] = useState(false);
    const [page, setPage] = useState<TicketPageContext>();

    const active = ticketing.enabled && isAuthenticated;

    useEffect(() => {
        if (!active) {
            return;
        }

        return installClientErrorCollector();
    }, [active]);

    if (!active) {
        return null;
    }

    const label = t('ticketing.title', 'Report an issue');

    return (
        <>
            <Tooltip content={label} side="right">
                <Button
                    type="button"
                    size="icon"
                    aria-label={label}
                    {...{[KEEP_MODAL_OPEN_ATTR]: ''}}
                    className={cn(
                        'pointer-events-auto fixed bottom-4 left-4 z-[60] size-11 rounded-full shadow-lg',
                        open && 'invisible'
                    )}
                    onClick={() => {
                        // Snapshot the page before the dialog covers it.
                        setPage(
                            collectPageContext({
                                locale: i18n.language,
                                dataLocale,
                                theme: resolvedTheme,
                            })
                        );
                        setOpen(true);
                    }}
                >
                    <LifeBuoyIcon className="size-5" />
                </Button>
            </Tooltip>
            <TicketDialog open={open} onOpenChange={setOpen} page={page} />
        </>
    );
}
