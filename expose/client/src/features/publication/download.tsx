'use client';

import {useCallback, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {CircleCheckIcon} from 'lucide-react';
import type {Publication} from '@/types/api';
import {FormDialog} from '@alchemy/next-ui/modals/FormDialog';
import {
    useModals,
    type ModalProps,
} from '@alchemy/next-ui/modals/ModalProvider';
import {FieldHelp, Input, Label} from '@alchemy/next-ui/ui/input';
import {
    getDownloadUrl,
    requestDownload,
    type DownloadTarget,
} from '@/lib/api/publications';
import {trackEvent} from '@alchemy/next-core/analytics/matomo';
import {acceptTerms, isTermsAccepted, termsKey} from './terms';
import {TermsContent} from './TermsContent';

type DownloadOptions = {
    /** The file, to get its download URL once the download terms are accepted */
    target: DownloadTarget;
    /** Opens the download in a new tab (the archive, built on demand) */
    newTab?: boolean;
    /** Name of the content downloaded, for the analytics */
    label?: string;
};

function openDownloadLink(url: string, newTab: boolean): void {
    const a = document.createElement('a');
    a.href = url;
    a.download = '';
    if (newTab) {
        a.target = '_blank';
        a.rel = 'noopener noreferrer';
    }
    document.body.appendChild(a);
    a.click();
    a.remove();
}

/**
 * The download of a file of the publication (an asset, a rendition, the
 * archive): nothing when downloads are disabled; the download terms to
 * accept first, once; then either a link sent by email, or the file itself.
 * The URLs given by the API already point to the right endpoint (direct or
 * by email) and are signed. With download terms, the API only lets the file
 * be downloaded through a URL given in exchange of their acceptance.
 */
export function useDownload(publication: Publication) {
    const {openModal} = useModals();

    return useCallback(
        async (
            url: string,
            {target, newTab = false, label}: DownloadOptions
        ) => {
            if (!publication.downloadEnabled) {
                return;
            }
            const withTerms = !!publication.downloadTerms?.enabled;
            const key = termsKey('download', publication.id);
            if (withTerms && !isTermsAccepted(key)) {
                const accepted = await openModal(DownloadTermsDialog, {
                    publication,
                });
                if (!accepted) {
                    return;
                }
                acceptTerms(key);
            }
            trackEvent(
                'Download',
                publication.downloadViaEmail ? 'email' : 'direct',
                label
            );
            if (publication.downloadViaEmail) {
                openModal(DownloadViaEmailDialog, {
                    url,
                    acceptTerms: withTerms,
                });

                return;
            }
            openDownloadLink(
                withTerms ? await getDownloadUrl(publication.id, target) : url,
                newTab
            );
        },
        [publication, openModal]
    );
}

function DownloadTermsDialog({
    open,
    onOpenChange,
    resolve,
    publication,
}: ModalProps<boolean> & {publication: Publication}) {
    const {t} = useTranslation();

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            size="md"
            title={t('download_terms.title', 'Download Terms and Conditions')}
            submitLabel={t('download_terms.accept', 'Accept & Download')}
            onSubmit={() => resolve?.(true)}
        >
            <div data-testid="download-terms-dialog">
                <TermsContent
                    terms={publication.downloadTerms ?? {}}
                    linkLabel={t('download_terms.link', 'View Download Terms')}
                />
            </div>
        </FormDialog>
    );
}

function DownloadViaEmailDialog({
    open,
    onOpenChange,
    url,
    acceptTerms,
}: ModalProps & {url: string; acceptTerms: boolean}) {
    const {t} = useTranslation();
    const [email, setEmail] = useState('');
    const [sent, setSent] = useState(false);
    const valid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.trim());

    if (sent) {
        return (
            <FormDialog
                open={open}
                onOpenChange={onOpenChange}
                title={t('download_email.title', 'Download via email')}
                hideCancel
                submitLabel={t('common.close', 'Close')}
            >
                <p
                    data-testid="download-email-sent"
                    className="flex items-center gap-2 text-sm"
                >
                    <CircleCheckIcon className="size-5 shrink-0 text-success" />
                    {t(
                        'download_email.sent',
                        'You will receive your download link by email.'
                    )}
                </p>
            </FormDialog>
        );
    }

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title={t('download_email.title', 'Download via email')}
            submitLabel={t('download_email.submit', 'Request Download')}
            canSubmit={valid}
            onSubmit={async () => {
                await requestDownload(url, email.trim(), acceptTerms);
                setSent(true);

                return false;
            }}
        >
            <div data-testid="download-email-dialog">
                <Label htmlFor="download-email" className="mb-1.5">
                    {t('download_email.email', 'Email')}
                </Label>
                <Input
                    id="download-email"
                    type="email"
                    autoFocus
                    required
                    autoComplete="email"
                    value={email}
                    onChange={e => setEmail(e.target.value)}
                />
                <FieldHelp>
                    {t(
                        'download_email.help',
                        'A link to download the file will be sent to this address.'
                    )}
                </FieldHelp>
            </div>
        </FormDialog>
    );
}
