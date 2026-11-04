'use client';

import {useTranslation} from 'react-i18next';
import {PaperclipIcon} from 'lucide-react';
import type {Share} from '@/types/api';
import {formatFileSize} from '@/lib/utils/format';

export function ShareAttachments({
    attachments,
}: {
    attachments: NonNullable<Share['attachments']>;
}) {
    const {t, i18n} = useTranslation();

    return (
        <section data-testid="share-attachments">
            <h2 className="mb-2 text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                {t('share.attachments.title', 'Attached files')}
            </h2>
            <ul className="divide-y rounded-lg border bg-card">
                {attachments.map(a => (
                    <li key={a.id}>
                        <a
                            href={a.url}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="flex items-center gap-3 px-3 py-2 text-sm hover:bg-accent"
                        >
                            <PaperclipIcon className="size-4 shrink-0 text-muted-foreground" />
                            <span className="min-w-0 flex-1 truncate">
                                {a.name || t('share.attachments.file', 'File')}
                            </span>
                            {a.size ? (
                                <span className="text-xs text-muted-foreground tabular-nums">
                                    {formatFileSize(
                                        a.size,
                                        true,
                                        i18n.language
                                    )}
                                </span>
                            ) : null}
                        </a>
                    </li>
                ))}
            </ul>
        </section>
    );
}
