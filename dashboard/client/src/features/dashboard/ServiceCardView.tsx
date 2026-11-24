'use client';

import {useTranslation} from 'react-i18next';
import {
    BookOpenIcon,
    CrownIcon,
    ShieldCheckIcon,
    type LucideIcon,
} from 'lucide-react';
import {Button} from '@alchemy/next-ui/ui/button';
import {Tooltip} from '@alchemy/next-ui/ui/overlays';
import {cn} from '@alchemy/next-ui/utils/cn';
import type {ServiceStatus} from '@/features/status/types';
import type {ActionId, CardId, ServiceCard} from './services';

/** The image of each card, or an icon when there is no logo */
const logos: Record<CardId, string | LucideIcon> = {
    iam: '/images/keycloak.png',
    databox: '/images/databox.svg',
    expose: '/images/expose.svg',
    uploader: '/images/uploader.svg',
    parade: '/images/parade.svg',
    consent: '/images/consent.svg',
    lend: '/images/lend.svg',
    order: '/images/order.svg',
    documentation: BookOpenIcon,
    dashboard: '/images/dashboard.svg',
};

const actionIcons: Record<ActionId, LucideIcon> = {
    'master-admin': CrownIcon,
    'admin': ShieldCheckIcon,
    'api-doc': BookOpenIcon,
};

function useCardTexts(): Record<CardId, {title: string; description: string}> {
    const {t} = useTranslation();

    return {
        iam: {
            title: t('service.iam.title', 'Identity Manager'),
            description: t('service.iam.description', 'Keycloak IAM'),
        },
        databox: {
            title: 'Databox',
            description: t('service.databox.description', 'Your DAM'),
        },
        expose: {
            title: 'Expose',
            description: t('service.expose.description', 'Share publications'),
        },
        uploader: {
            title: 'Uploader',
            description: t(
                'service.uploader.description',
                'Standalone asset deposit'
            ),
        },
        parade: {
            title: 'Parade',
            description: t('service.parade.description', 'Public showcase'),
        },
        consent: {
            title: 'Consent',
            description: t(
                'service.consent.description',
                'Image rights consent'
            ),
        },
        lend: {
            title: 'Lend',
            description: t('service.lend.description', 'Asset lending'),
        },
        order: {
            title: 'Order',
            description: t('service.order.description', 'Media orders'),
        },
        documentation: {
            title: t('service.documentation.title', 'Documentation'),
            description: t(
                'service.documentation.description',
                'Phrasea user guide'
            ),
        },
        dashboard: {
            title: t('app.title', 'Dashboard'),
            description: t(
                'service.dashboard.description',
                'Phrasea entry point'
            ),
        },
    };
}

function StatusIndicator({status}: {status: ServiceStatus}) {
    const {t} = useTranslation();
    const labels: Record<ServiceStatus, string> = {
        up: t('status.up', 'Operational'),
        degraded: t('status.degraded', 'Degraded'),
        down: t('status.down', 'Unavailable'),
    };

    return (
        <span
            data-testid="service-status"
            data-status={status}
            className="inline-flex min-w-0 items-center gap-1.5 px-1 text-xs text-muted-foreground"
        >
            <span
                aria-hidden
                className={cn(
                    'size-2 shrink-0 rounded-full',
                    status === 'up' && 'bg-success',
                    status === 'degraded' && 'bg-warning',
                    status === 'down' && 'bg-destructive'
                )}
            />
            <span className="truncate">{labels[status]}</span>
        </span>
    );
}

/**
 * A service: the whole card opens it (a stretched link over the title), the
 * actions of the footer sit above that link.
 */
export function ServiceCardView({
    card,
    status,
    target,
}: {
    card: ServiceCard;
    status?: ServiceStatus;
    target?: string;
}) {
    const {t} = useTranslation();
    const {title, description} = useCardTexts()[card.id];
    const actionLabels: Record<ActionId, string> = {
        'master-admin': t('service.action.master_admin', 'Master admin'),
        'admin': t('service.action.admin', 'Admin'),
        'api-doc': t('service.action.api_doc', 'API documentation'),
    };
    const unavailable = status === 'down';
    const Logo = logos[card.id];

    return (
        <li
            data-testid={`service-${card.id}`}
            className={cn(
                'relative flex flex-col overflow-hidden rounded-lg border bg-card text-card-foreground shadow-xs transition-shadow',
                card.href &&
                    'focus-within:ring-2 focus-within:ring-ring/60 hover:shadow-md'
            )}
        >
            <div className="flex h-16 items-center justify-center bg-muted/60 p-3 sm:h-20 md:h-32 embedded:h-16">
                {typeof Logo === 'string' ? (
                    // eslint-disable-next-line @next/next/no-img-element
                    <img
                        src={Logo}
                        alt=""
                        className={cn(
                            'max-h-full max-w-full object-contain',
                            unavailable && 'opacity-50 grayscale'
                        )}
                    />
                ) : (
                    <Logo
                        aria-hidden
                        className={cn(
                            'size-10 text-muted-foreground md:size-16 embedded:size-10',
                            unavailable && 'opacity-50 grayscale'
                        )}
                    />
                )}
            </div>
            <div className="flex flex-1 flex-col gap-1 p-3">
                <h2 className="text-base font-semibold">
                    {card.href ? (
                        <a
                            href={card.href}
                            target={target}
                            className="outline-none after:absolute after:inset-0"
                        >
                            {title}
                        </a>
                    ) : (
                        title
                    )}
                </h2>
                <p className="hidden text-sm text-muted-foreground md:block embedded:hidden">
                    {description}
                </p>
            </div>
            {status || card.actions.length > 0 ? (
                <div className="relative flex min-h-10 items-center gap-1 border-t px-2 py-1">
                    {status ? <StatusIndicator status={status} /> : null}
                    <div className="ml-auto flex items-center">
                        {card.actions.map(action => {
                            const Icon = actionIcons[action.id];

                            return (
                                <Tooltip
                                    key={action.id}
                                    content={actionLabels[action.id]}
                                >
                                    <Button
                                        variant="ghost"
                                        size="icon-xs"
                                        asChild
                                    >
                                        <a
                                            href={action.href}
                                            target={target}
                                            data-testid={`action-${action.id}`}
                                            aria-label={actionLabels[action.id]}
                                        >
                                            <Icon />
                                        </a>
                                    </Button>
                                </Tooltip>
                            );
                        })}
                    </div>
                </div>
            ) : null}
        </li>
    );
}
