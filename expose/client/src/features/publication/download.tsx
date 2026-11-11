'use client';

import {useCallback, useEffect, useEffectEvent, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {CircleCheckIcon} from 'lucide-react';
import type {Publication} from '@/types/api';
import {FormDialog} from '@alchemy/next-ui/modals/FormDialog';
import {
    useModals,
    type ModalProps,
} from '@alchemy/next-ui/modals/ModalProvider';
import {FieldHelp, Input, Label} from '@alchemy/next-ui/ui/input';
import {Spinner} from '@alchemy/next-ui/ui/loader';
import {toastError} from '@alchemy/next-ui/utils/errors';
import {
    getArchiveStatus,
    getDownloadUrl,
    requestArchiveByEmail,
    requestDownload,
    type ArchiveStatus,
    type DownloadTarget,
} from '@/lib/api/publications';
import {trackEvent} from '@alchemy/next-core/analytics/matomo';
import {acceptTerms, isTermsAccepted, termsKey} from './terms';
import {TermsContent} from './TermsContent';
import {waitForArchive} from './archive';

type DownloadOptions = {
    /** The file, to get its download URL once the download terms are accepted */
    target: DownloadTarget;
    /** Name of the content downloaded, for the analytics */
    label?: string;
};

function openDownloadLink(url: string): void {
    const a = document.createElement('a');
    a.href = url;
    a.download = '';
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
 *
 * The archive is built by the API on demand: while it is prepared, a dialog
 * waits for it, and offers to receive its link by email instead.
 */
export function useDownload(publication: Publication) {
    const {openModal} = useModals();

    return useCallback(
        async (url: string, {target, label}: DownloadOptions) => {
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
                    request: email => requestDownload(url, email, withTerms),
                });

                return;
            }
            const downloadUrl = withTerms
                ? await getDownloadUrl(publication.id, target)
                : url;
            if (!('archive' in target)) {
                openDownloadLink(downloadUrl);

                return;
            }

            try {
                const status = await getArchiveStatus(downloadUrl);
                if (status.status === 'ready') {
                    openDownloadLink(status.url);

                    return;
                }
            } catch (error) {
                toastError(error);

                return;
            }
            openModal(ArchiveDownloadDialog, {
                check: () => getArchiveStatus(downloadUrl),
                requestByEmail: email =>
                    requestArchiveByEmail(publication.id, email, withTerms),
            });
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

/**
 * Waits for the archive being prepared by the API, then downloads it; the
 * reader may rather receive its link by email.
 */
function ArchiveDownloadDialog({
    open,
    onOpenChange,
    check,
    requestByEmail,
}: ModalProps & {
    check: () => Promise<ArchiveStatus>;
    requestByEmail: (email: string) => Promise<void>;
}) {
    const {t} = useTranslation();
    const {openModal} = useModals();
    const [timedOut, setTimedOut] = useState(false);

    const checkArchive = useEffectEvent(() => check());
    const onReady = useEffectEvent((url: string) => {
        openDownloadLink(url);
        onOpenChange(false);
    });
    const onError = useEffectEvent((error: unknown) => {
        toastError(error);
        onOpenChange(false);
    });

    useEffect(() => {
        if (!open) {
            return;
        }
        const controller = new AbortController();
        waitForArchive(checkArchive, {signal: controller.signal}).then(
            result => {
                if (result.status === 'ready') {
                    onReady(result.url);
                } else {
                    setTimedOut(true);
                }
            },
            error => {
                if (!controller.signal.aborted) {
                    onError(error);
                }
            }
        );

        return () => controller.abort();
    }, [open]);

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title={t('download_archive.title', 'Download Archive')}
            submitLabel={t('download_archive.by_email', 'Receive by email')}
            onSubmit={() => {
                openModal(DownloadViaEmailDialog, {request: requestByEmail});
            }}
        >
            <div
                data-testid="download-archive-dialog"
                className="flex items-center gap-3 text-sm"
            >
                {timedOut ? (
                    <p data-testid="download-archive-timeout">
                        {t(
                            'download_archive.timeout',
                            'The archive takes longer than expected to prepare. Try again in a few minutes, or receive its link by email.'
                        )}
                    </p>
                ) : (
                    <>
                        <Spinner className="size-5 shrink-0" />
                        <p data-testid="download-archive-preparing">
                            {t(
                                'download_archive.preparing',
                                'The archive is being prepared. Its download will start as soon as it is ready.'
                            )}
                        </p>
                    </>
                )}
            </div>
        </FormDialog>
    );
}

function DownloadViaEmailDialog({
    open,
    onOpenChange,
    request,
}: ModalProps & {request: (email: string) => Promise<void>}) {
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
                await request(email.trim());
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
