'use client';

import {useTranslation} from 'react-i18next';
import {ExternalLinkIcon} from 'lucide-react';
import type {TermsConfig} from '@/types/api';
import {RichText} from '@/components/RichText';

/** The text of terms and/or the link to them */
export function TermsContent({
    terms,
    linkLabel,
}: {
    terms: TermsConfig;
    linkLabel: string;
}) {
    const {t} = useTranslation();

    return (
        <div className="space-y-3">
            {terms.text ? (
                <RichText
                    html={terms.text}
                    data-testid="terms-text"
                    className="max-h-[50dvh] overflow-auto rounded-md border bg-muted/30 p-3 text-sm"
                />
            ) : (
                <p className="text-sm text-muted-foreground">
                    {t(
                        'terms.read_and_accept',
                        'Please read and accept the terms.'
                    )}
                </p>
            )}
            {terms.url ? (
                <a
                    href={terms.url}
                    target="_blank"
                    rel="noopener noreferrer"
                    data-testid="terms-link"
                    className="inline-flex items-center gap-1.5 text-sm font-medium text-primary hover:underline"
                >
                    {linkLabel} <ExternalLinkIcon className="size-3.5" />
                </a>
            ) : null}
        </div>
    );
}
