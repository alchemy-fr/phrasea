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

const logos: Record<CardId, string> = {
    iam: '/images/keycloak.png',
    databox: '/images/databox.png',
    expose: '/images/expose.png',
    uploader: '/images/uploader.png',
    dashboard: '/images/dashboard.png',
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
                {/* eslint-disable-next-line @next/next/no-img-element */}
                <img
                    src={logos[card.id]}
                    alt=""
                    className={cn(
                        'max-h-full max-w-full object-contain',
                        unavailable && 'opacity-50 grayscale'
                    )}
                />
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
