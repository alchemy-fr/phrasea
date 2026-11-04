'use client';

import Link from 'next/link';
import {useTranslation} from 'react-i18next';
import {EyeOffIcon, PencilIcon, Trash2Icon} from 'lucide-react';
import type {Publication} from '@/types/api';
import {Button} from '@/components/ui/button';
import {Tooltip} from '@/components/ui/overlays';
import {RichText} from '@/components/RichText';
import {getThumbSrc} from '@/features/publication/Thumbnail';
import {useTranslateField} from '@/lib/utils/translate';
import {routes} from '@/lib/routes';
import {cn} from '@/lib/utils/cn';
import {useDeletePublication} from './usePublicationActions';

export function PublicationCard({publication}: {publication: Publication}) {
    const {t} = useTranslation();
    const translate = useTranslateField();
    const remove = useDeletePublication();
    const title = translate(publication, 'title');
    const cover = publication.cover
        ? getThumbSrc(publication.cover)
        : undefined;
    const disabled = publication.enabled === false;
    const unlisted = publication.publiclyListed === false;
    const hiddenReasons = [
        disabled &&
            t(
                'publication.hidden.disabled',
                'This publication is disabled: only its operators can access it.'
            ),
        unlisted &&
            t(
                'publication.hidden.unlisted',
                'This publication is not listed publicly: you see it because of your permissions.'
            ),
    ].filter(Boolean);

    return (
        <article
            data-testid="publication-card"
            className="group relative flex h-full flex-col overflow-hidden rounded-xl border bg-card text-card-foreground shadow-xs transition-shadow focus-within:ring-2 focus-within:ring-ring hover:shadow-md"
        >
            <div className="relative h-[194px] shrink-0 bg-muted">
                {cover ? (
                    // eslint-disable-next-line @next/next/no-img-element
                    <img
                        src={cover}
                        alt=""
                        loading="lazy"
                        className={cn(
                            'size-full object-cover transition-transform duration-300 group-hover:scale-105 motion-reduce:transition-none',
                            disabled && 'opacity-50 grayscale'
                        )}
                    />
                ) : null}
                {hiddenReasons.length > 0 ? (
                    <Tooltip content={hiddenReasons.join(' ')}>
                        <span
                            data-testid="publication-hidden"
                            className="absolute top-2 left-2 z-10 flex size-8 items-center justify-center rounded-full bg-background/90 shadow"
                        >
                            <EyeOffIcon className="size-4" />
                        </span>
                    </Tooltip>
                ) : null}
            </div>
            <div className="flex flex-1 flex-col gap-2 p-4">
                <h2 className="font-semibold break-words">
                    {/* The whole card is the link */}
                    <Link
                        href={routes.publication(publication)}
                        className="outline-none after:absolute after:inset-0"
                    >
                        {title}
                    </Link>
                </h2>
                <RichText
                    html={translate(publication, 'description')}
                    className="line-clamp-3 text-sm text-muted-foreground"
                />
            </div>
            {publication.capabilities?.edit ||
            publication.capabilities?.delete ? (
                <div className="relative z-10 flex justify-end gap-1 border-t px-2 py-1.5">
                    {publication.capabilities.edit ? (
                        <Button variant="ghost" size="sm" asChild>
                            <Link
                                href={routes.publicationEdit(publication.id)}
                                data-testid="publication-card-edit"
                            >
                                <PencilIcon /> {t('common.edit', 'Edit')}
                            </Link>
                        </Button>
                    ) : null}
                    {publication.capabilities.delete ? (
                        <Button
                            variant="ghost"
                            size="sm"
                            className="text-destructive hover:text-destructive"
                            data-testid="publication-card-delete"
                            onClick={() => remove(publication)}
                        >
                            <Trash2Icon /> {t('common.delete', 'Delete')}
                        </Button>
                    ) : null}
                </div>
            ) : null}
        </article>
    );
}
