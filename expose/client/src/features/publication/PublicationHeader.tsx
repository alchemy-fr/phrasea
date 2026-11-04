'use client';

import {Fragment} from 'react';
import Link from 'next/link';
import {useTranslation} from 'react-i18next';
import {
    ChevronRightIcon,
    EyeOffIcon,
    HouseIcon,
    PackageIcon,
    PencilIcon,
} from 'lucide-react';
import type {Publication, PublicationRef} from '@/types/api';
import {Button} from '@alchemy/next-ui/ui/button';
import {Alert} from '@alchemy/next-ui/ui/misc';
import {RichText} from '@/components/RichText';
import {useConfig} from '@/lib/config/ConfigProvider';
import {useTranslateField} from '@/lib/utils/translate';
import {formatDateTime} from '@/lib/utils/format';
import {routes} from '@/lib/routes';
import {useDownload} from './download';

/**
 * The ancestors known of a publication, from the root: the API gives its
 * root and its direct parent only, a gap between them is shown as "…".
 */
export function getAncestors(publication: Publication): {
    root?: PublicationRef;
    gap: boolean;
    parent?: PublicationRef;
} {
    const {parent, rootPublication: root} = publication;
    if (!parent) {
        return {gap: false};
    }
    if (!root || root.id === parent.id) {
        return {parent, gap: false};
    }

    return {root, parent, gap: parent.parentId !== root.id};
}

export function PublicationHeader({publication}: {publication: Publication}) {
    const {t, i18n} = useTranslation();
    const config = useConfig();
    const translate = useTranslateField();
    const download = useDownload(publication);
    const title = translate(publication, 'title');
    const description = translate(publication, 'description');
    const {root, gap, parent} = getAncestors(publication);
    const date = formatDateTime(publication.date, 'long', i18n.language, false);
    const logoUrl = publication.layoutOptions?.logoUrl;
    const canDownloadArchive =
        publication.downloadEnabled &&
        !!publication.archiveDownloadUrl &&
        (publication.assets?.length ?? 0) > 1;

    const crumb = (p: PublicationRef) => (
        <Link
            href={routes.publication(p)}
            className="max-w-[24ch] truncate rounded px-1 hover:bg-accent hover:text-foreground"
        >
            {translate(p, 'title') || p.slug || p.id}
        </Link>
    );

    return (
        <header
            data-testid="publication-header"
            className="flex flex-col gap-3"
        >
            {logoUrl ? (
                // eslint-disable-next-line @next/next/no-img-element
                <img
                    src={logoUrl}
                    alt=""
                    data-testid="publication-logo"
                    className="max-h-[150px] self-start object-contain"
                />
            ) : null}
            {!config.disableIndexPage || parent ? (
                <nav
                    aria-label={t('nav.breadcrumb', 'Breadcrumb')}
                    data-testid="breadcrumb"
                    className="flex flex-wrap items-center gap-1 text-sm text-muted-foreground"
                >
                    {!config.disableIndexPage ? (
                        <Link
                            href={routes.home()}
                            aria-label={t('nav.home', 'Home')}
                            className="rounded p-1 hover:bg-accent hover:text-foreground"
                        >
                            <HouseIcon className="size-4" />
                        </Link>
                    ) : null}
                    {[root, gap ? null : undefined, parent].map((p, i) =>
                        p === undefined ? null : (
                            <Fragment key={i}>
                                {i > 0 || !config.disableIndexPage ? (
                                    <ChevronRightIcon className="size-3.5 shrink-0" />
                                ) : null}
                                {p === null ? <span>…</span> : crumb(p)}
                            </Fragment>
                        )
                    )}
                </nav>
            ) : null}
            <div className="flex flex-wrap items-start gap-x-3 gap-y-1">
                <h1
                    data-testid="publication-title"
                    className="min-w-0 text-2xl font-bold tracking-tight break-words sm:text-3xl"
                >
                    {title}
                </h1>
                {publication.capabilities?.edit ? (
                    <Button variant="outline" size="sm" asChild>
                        <Link
                            href={routes.publicationEdit(publication.id)}
                            data-testid="publication-edit"
                        >
                            <PencilIcon /> {t('common.edit', 'Edit')}
                        </Link>
                    </Button>
                ) : null}
            </div>
            {date ? (
                <time
                    dateTime={publication.date ?? undefined}
                    className="text-sm text-muted-foreground"
                >
                    {date}
                </time>
            ) : null}
            {publication.enabled === false ? (
                <Alert
                    variant="warning"
                    icon={<EyeOffIcon />}
                    data-testid="publication-disabled"
                >
                    {t(
                        'publication.disabled_warning',
                        'This publication is currently disabled. Only administrators can see it.'
                    )}
                </Alert>
            ) : null}
            {description || canDownloadArchive ? (
                <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                    <RichText
                        html={description}
                        data-testid="publication-description"
                        className="min-w-0 flex-1 text-muted-foreground"
                    />
                    {canDownloadArchive ? (
                        <Button
                            variant="outline"
                            className="shrink-0 self-start"
                            data-testid="download-archive"
                            onClick={() =>
                                download(publication.archiveDownloadUrl!, {
                                    newTab: true,
                                    label: `${title} (archive)`,
                                })
                            }
                        >
                            <PackageIcon />{' '}
                            {t(
                                'publication.download_archive',
                                'Download Archive'
                            )}
                        </Button>
                    ) : null}
                </div>
            ) : null}
        </header>
    );
}
