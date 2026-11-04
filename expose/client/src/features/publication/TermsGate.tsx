'use client';

import {PropsWithChildren, useState} from 'react';
import {useTranslation} from 'react-i18next';
import type {Publication} from '@/types/api';
import {Button} from '@alchemy/next-ui/ui/button';
import {
    Dialog,
    DialogBody,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@alchemy/next-ui/ui/dialog';
import {acceptTerms, isTermsAccepted, termsKey} from './terms';
import {TermsContent} from './TermsContent';

/**
 * Asks to accept the terms of use of the publication before showing it.
 * Until then, the content is not rendered at all: no media is loaded behind
 * the dialog. The acceptance is remembered for the publication.
 */
export function TermsGate({
    publication,
    children,
}: PropsWithChildren<{publication: Publication}>) {
    const {t} = useTranslation();
    const key = termsKey('view', publication.id);
    const [accepted, setAccepted] = useState(() => isTermsAccepted(key));

    if (!publication.terms?.enabled || accepted) {
        return <>{children}</>;
    }

    return (
        <>
            <div
                aria-hidden
                className="min-h-[70dvh] bg-[radial-gradient(circle_at_30%_20%,var(--muted),transparent_60%)] blur-2xl"
            />
            <Dialog open>
                <DialogContent
                    size="md"
                    data-testid="terms-dialog"
                    hideClose
                    aria-describedby={undefined}
                    onEscapeKeyDown={e => e.preventDefault()}
                    onInteractOutside={e => e.preventDefault()}
                >
                    <DialogHeader>
                        <DialogTitle>
                            {t('terms.title', 'Terms and Conditions')}
                        </DialogTitle>
                    </DialogHeader>
                    <DialogBody>
                        <TermsContent
                            terms={publication.terms}
                            linkLabel={t('terms.link', 'Terms')}
                        />
                    </DialogBody>
                    <DialogFooter>
                        <Button
                            data-testid="terms-accept"
                            onClick={() => {
                                acceptTerms(key);
                                setAccepted(true);
                            }}
                        >
                            {t('terms.accept', 'Accept')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
