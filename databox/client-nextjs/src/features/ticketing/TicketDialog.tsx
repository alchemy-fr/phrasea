'use client';

import {useEffect, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {toast} from 'sonner';
import {
    BugIcon,
    CameraIcon,
    ExternalLinkIcon,
    LightbulbIcon,
    MessageCircleQuestionIcon,
    Trash2Icon,
} from 'lucide-react';
import {
    Dialog,
    DialogBody,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {Button} from '@/components/ui/button';
import {FormRow, Input, Textarea} from '@/components/ui/input';
import {SimpleSelect} from '@/components/ui/select';
import {
    Accordion,
    AccordionContent,
    AccordionItem,
    AccordionTrigger,
} from '@/components/ui/misc';
import {useAuth} from '@/lib/auth/AuthProvider';
import {createTicket} from './api';
import {
    captureScreenshot,
    imageFileToDataUrl,
    isScreenCaptureSupported,
} from './screenshot';
import {
    descriptionMaxLength,
    summaryMaxLength,
    type TicketKind,
    type TicketPageContext,
} from './types';

const kindIcons: Record<TicketKind, typeof BugIcon> = {
    bug: BugIcon,
    improvement: LightbulbIcon,
    question: MessageCircleQuestionIcon,
};

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** Page snapshot, taken when the button was clicked */
    page: TicketPageContext | undefined;
};

export function TicketDialog({open, onOpenChange, page}: Props) {
    const {t} = useTranslation();
    const {user} = useAuth();
    const [kind, setKind] = useState<TicketKind>('bug');
    const [summary, setSummary] = useState('');
    const [description, setDescription] = useState('');
    const [screenshot, setScreenshot] = useState<string | undefined>();
    const [capturing, setCapturing] = useState(false);
    // Resolved on the client only: `navigator` does not exist while rendering
    // on the server.
    const [canCapture, setCanCapture] = useState(false);
    const [submitting, setSubmitting] = useState(false);

    useEffect(() => setCanCapture(isScreenCaptureSupported()), []);

    useEffect(() => {
        if (open) {
            setKind('bug');
            setSummary('');
            setDescription('');
            setScreenshot(undefined);
        }
    }, [open]);

    // Pasting an image (Ctrl+V) attaches it as the screenshot.
    useEffect(() => {
        if (!open) {
            return;
        }
        const onPaste = (e: ClipboardEvent) => {
            const file = Array.from(e.clipboardData?.files ?? []).find(f =>
                f.type.startsWith('image/')
            );
            if (!file) {
                return;
            }
            e.preventDefault();
            imageFileToDataUrl(file).then(setScreenshot, (err: Error) =>
                toast.error(err.message)
            );
        };
        document.addEventListener('paste', onPaste);

        return () => document.removeEventListener('paste', onPaste);
    }, [open]);

    const capture = async () => {
        setCapturing(true);
        try {
            setScreenshot(await captureScreenshot());
        } catch (e: any) {
            // The user cancelled the browser picker: nothing to report.
            if (e?.name !== 'NotAllowedError' && e?.name !== 'AbortError') {
                toast.error(
                    e?.message ??
                        t(
                            'ticketing.capture_failed',
                            'Unable to capture the screen'
                        )
                );
            }
        } finally {
            setCapturing(false);
        }
    };

    const submit = async () => {
        if (!page) {
            return;
        }
        setSubmitting(true);
        try {
            const ticket = await createTicket({
                kind,
                summary: summary.trim(),
                description: description.trim(),
                page,
                screenshot,
            });
            toast.success(
                t('ticketing.created', 'Ticket {{key}} created', {
                    key: ticket.key,
                }),
                {
                    action: {
                        label: t('ticketing.open_ticket', 'Open'),
                        onClick: () => window.open(ticket.url, '_blank'),
                    },
                }
            );
            if (screenshot && !ticket.screenshotAttached) {
                toast.warning(
                    t(
                        'ticketing.screenshot_failed',
                        'The ticket was created but the screenshot could not be attached'
                    )
                );
            }
            onOpenChange(false);
        } catch (e: any) {
            toast.error(e?.message);
        } finally {
            setSubmitting(false);
        }
    };

    const KindIcon = kindIcons[kind];

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent size="md">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <KindIcon className="size-4" />
                        {t('ticketing.title', 'Report an issue')}
                    </DialogTitle>
                    <DialogDescription>
                        {t(
                            'ticketing.description',
                            'Your report is sent to our issue tracker along with the page you are on and your session.'
                        )}
                    </DialogDescription>
                </DialogHeader>
                <DialogBody>
                    <FormRow label={t('ticketing.kind', 'Type')}>
                        <SimpleSelect<TicketKind>
                            value={kind}
                            onValueChange={setKind}
                            options={[
                                {
                                    value: 'bug',
                                    label: t('ticketing.kind.bug', 'Bug'),
                                },
                                {
                                    value: 'improvement',
                                    label: t(
                                        'ticketing.kind.improvement',
                                        'Improvement'
                                    ),
                                },
                                {
                                    value: 'question',
                                    label: t(
                                        'ticketing.kind.question',
                                        'Question'
                                    ),
                                },
                            ]}
                        />
                    </FormRow>
                    <FormRow
                        label={t('ticketing.summary', 'Summary')}
                        htmlFor="ticket-summary"
                    >
                        <Input
                            id="ticket-summary"
                            autoFocus
                            maxLength={summaryMaxLength}
                            value={summary}
                            placeholder={t(
                                'ticketing.summary_placeholder',
                                'Short title of the problem'
                            )}
                            onChange={e => setSummary(e.target.value)}
                        />
                    </FormRow>
                    <FormRow
                        label={t('ticketing.details', 'Details')}
                        htmlFor="ticket-description"
                        help={t(
                            'ticketing.details_help',
                            'What did you do, what did you expect, what happened?'
                        )}
                    >
                        <Textarea
                            id="ticket-description"
                            rows={6}
                            maxLength={descriptionMaxLength}
                            value={description}
                            onChange={e => setDescription(e.target.value)}
                        />
                    </FormRow>
                    <FormRow
                        label={t('ticketing.screenshot', 'Screenshot')}
                        help={
                            screenshot
                                ? undefined
                                : canCapture
                                  ? t(
                                        'ticketing.screenshot_help',
                                        'Optional: capture this page, or paste an image (Ctrl+V).'
                                    )
                                  : t(
                                        'ticketing.screenshot_paste_help',
                                        'Optional: paste an image (Ctrl+V).'
                                    )
                        }
                    >
                        {screenshot ? (
                            <div className="flex items-start gap-3">
                                {/* eslint-disable-next-line @next/next/no-img-element */}
                                <img
                                    src={screenshot}
                                    alt={t(
                                        'ticketing.screenshot',
                                        'Screenshot'
                                    )}
                                    className="max-h-40 rounded-md border object-contain"
                                />
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon-sm"
                                    aria-label={t('common.remove', 'Remove')}
                                    onClick={() => setScreenshot(undefined)}
                                >
                                    <Trash2Icon />
                                </Button>
                            </div>
                        ) : canCapture ? (
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                loading={capturing}
                                onClick={capture}
                            >
                                <CameraIcon />
                                {t('ticketing.capture', 'Capture this page')}
                            </Button>
                        ) : null}
                    </FormRow>
                    <Accordion type="single" collapsible className="border-t">
                        <AccordionItem value="context">
                            <AccordionTrigger>
                                {t(
                                    'ticketing.context',
                                    'Data attached to the ticket'
                                )}
                            </AccordionTrigger>
                            <AccordionContent>
                                <dl className="grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 text-xs text-muted-foreground">
                                    <dt>
                                        {t('ticketing.context.page', 'Page')}
                                    </dt>
                                    <dd className="truncate">{page?.url}</dd>
                                    <dt>
                                        {t('ticketing.context.user', 'User')}
                                    </dt>
                                    <dd className="truncate">
                                        {user?.name ?? user?.username}
                                        {user?.email ? ` (${user.email})` : ''}
                                    </dd>
                                    <dt>
                                        {t(
                                            'ticketing.context.browser',
                                            'Browser'
                                        )}
                                    </dt>
                                    <dd className="truncate">
                                        {page?.userAgent}
                                    </dd>
                                    {page?.errors?.length ? (
                                        <>
                                            <dt>
                                                {t(
                                                    'ticketing.context.errors',
                                                    'Console errors'
                                                )}
                                            </dt>
                                            <dd>{page.errors.length}</dd>
                                        </>
                                    ) : null}
                                </dl>
                            </AccordionContent>
                        </AccordionItem>
                    </Accordion>
                </DialogBody>
                <DialogFooter>
                    <Button
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                    >
                        {t('common.cancel', 'Cancel')}
                    </Button>
                    <Button
                        onClick={submit}
                        loading={submitting}
                        disabled={
                            !summary.trim() || !description.trim() || !page
                        }
                    >
                        <ExternalLinkIcon />
                        {t('ticketing.submit', 'Create ticket')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
