'use client';

import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {FileTextIcon} from 'lucide-react';
import {Button} from '@/components/ui/button';
import {Checkbox, LabeledControl} from '@/components/ui/controls';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type {ShareTerms} from '@/types/api';

/** The terms text, or a link to their PDF when provided instead */
export function TermsContent({
    terms,
}: {
    terms: {text?: string | null; pdfUrl?: string | null};
}) {
    const {t} = useTranslation();

    return terms.pdfUrl ? (
        <Button variant="outline" size="sm" asChild>
            <a href={terms.pdfUrl} target="_blank" rel="noopener noreferrer">
                <FileTextIcon />{' '}
                {t('share.terms.view_pdf', 'View Terms & Conditions (PDF)')}
            </a>
        </Button>
    ) : (
        <div className="max-h-80 overflow-y-auto rounded-md border p-3 text-sm whitespace-pre-wrap">
            {terms.text}
        </div>
    );
}

export function ShareTermsDialog({
    terms,
    onAccept,
}: {
    terms: ShareTerms;
    onAccept: () => void;
}) {
    const {t} = useTranslation();
    const [accepted, setAccepted] = useState(false);

    return (
        <Dialog open>
            <DialogContent
                hideClose
                size="lg"
                data-testid="share-terms-dialog"
                onEscapeKeyDown={e => e.preventDefault()}
                onInteractOutside={e => e.preventDefault()}
            >
                <DialogHeader>
                    <DialogTitle>
                        {t('share.terms.title', 'Terms & Conditions')}
                    </DialogTitle>
                    <DialogDescription>
                        {t(
                            'share.terms.dialog_intro',
                            'You must accept the Terms & Conditions of {{workspace}} (version {{version}}) to access this content.',
                            {
                                workspace: terms.workspaceName,
                                version: terms.version,
                            }
                        )}
                    </DialogDescription>
                </DialogHeader>
                <TermsContent terms={terms} />
                <LabeledControl
                    label={t(
                        'share.terms.accept_label',
                        'I have read and accept the Terms & Conditions'
                    )}
                >
                    <Checkbox
                        checked={accepted}
                        onCheckedChange={v => setAccepted(v === true)}
                    />
                </LabeledControl>
                <DialogFooter>
                    <Button disabled={!accepted} onClick={onAccept}>
                        {t('share.terms.accept', 'Accept')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

export function ShareTermsSection({terms}: {terms: ShareTerms}) {
    const {t} = useTranslation();

    return (
        <section
            data-testid="share-terms"
            className="text-sm text-muted-foreground"
        >
            <h2 className="mb-2 text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                {t('share.terms.title', 'Terms & Conditions')}
            </h2>
            <TermsContent terms={terms} />
        </section>
    );
}

const termsStorageKey = (shareId: string) => `share.terms.accepted.${shareId}`;

/** Whether this terms version was already accepted on this browser */
export function hasAcceptedTerms(shareId: string, terms: ShareTerms): boolean {
    try {
        return (
            window.localStorage.getItem(termsStorageKey(shareId)) ===
            String(terms.version)
        );
    } catch {
        return false;
    }
}

export function storeAcceptedTerms(shareId: string, terms: ShareTerms): void {
    try {
        window.localStorage.setItem(
            termsStorageKey(shareId),
            String(terms.version)
        );
    } catch {
        // Storage unavailable (private mode…): accepted for this page only
    }
}
