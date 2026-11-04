'use client';

import Link from 'next/link';
import {useTranslation} from 'react-i18next';
import {CodeIcon, TagIcon, WrenchIcon} from 'lucide-react';
import {Alert, badgeVariants} from '@/components/ui/misc';
import {useAuth} from '@/lib/auth/AuthProvider';
import {useConfig} from '@/lib/config/ConfigProvider';
import {linkTarget, useEmbedded} from '@/lib/embedded';
import {routes} from '@/lib/routes';
import {cn} from '@/lib/utils/cn';
import type {ToolId} from '@/lib/config/types';
import {useServiceStatuses} from '@/features/status/useServiceStatuses';
import {getServiceCards, getTools} from './services';
import {ServiceCardView} from './ServiceCardView';
import {EmbeddedAccount} from './EmbeddedAccount';

const toolLabels: Record<ToolId, string> = {
    pgadmin: 'PgAdmin',
    phpmyadmin: 'PhpMyAdmin',
    mailpit: 'Mailpit',
    matomo: 'Matomo',
    rabbitmq: 'RabbitMQ',
    traefik: 'Traefik',
    sockudo: 'Sockudo',
};

export function DashboardScreen() {
    const {t} = useTranslation();
    const config = useConfig();
    const {user, isAuthenticated} = useAuth();
    const embedded = useEmbedded();
    const target = linkTarget(embedded);
    const {data: statuses} = useServiceStatuses();
    const roles = user?.roles ?? [];
    const cards = getServiceCards({
        config,
        roles,
        authenticated: isAuthenticated,
        embedded,
    });
    const tools = getTools(config.tools, roles);

    return (
        <div className="mx-auto flex w-full max-w-6xl flex-col gap-5 px-4 py-6 embedded:gap-3 embedded:p-3">
            <header className="flex flex-wrap items-center gap-3">
                <h1
                    data-testid="stack-name"
                    className="min-w-0 truncate text-3xl font-semibold embedded:text-xl"
                >
                    {config.stack.name ?? 'Phrasea'}
                </h1>
                {isAuthenticated && config.stack.version ? (
                    <Link
                        href={routes.gitLog()}
                        target={target}
                        data-testid="stack-version"
                        title={t('stack.version.title', 'Latest changes')}
                        className={cn(
                            badgeVariants({variant: 'secondary'}),
                            'hover:bg-secondary/80'
                        )}
                    >
                        <TagIcon /> {config.stack.version}
                    </Link>
                ) : null}
                <div className="ml-auto hidden min-w-0 embedded:flex">
                    <EmbeddedAccount />
                </div>
            </header>

            {isAuthenticated && config.devMode ? (
                <Alert
                    variant="info"
                    icon={<CodeIcon />}
                    data-testid="dev-mode"
                    className="embedded:hidden"
                >
                    {t('stack.dev_mode', 'Developer mode is enabled')}
                </Alert>
            ) : null}

            <ul
                data-testid="services"
                aria-label={t('services.title', 'Services')}
                className="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 embedded:gap-2"
            >
                {cards.map(card => (
                    <ServiceCardView
                        key={card.id}
                        card={card}
                        target={target}
                        status={
                            card.statusOf
                                ? statuses?.[card.statusOf]
                                : undefined
                        }
                    />
                ))}
            </ul>

            {tools.length > 0 ? (
                <section
                    data-testid="tools"
                    aria-labelledby="tools-title"
                    className="flex flex-col gap-2"
                >
                    <h2
                        id="tools-title"
                        className="flex items-center gap-2 text-sm font-medium text-muted-foreground"
                    >
                        <WrenchIcon className="size-4" />
                        {t('tools.title', 'Technical tools')}
                    </h2>
                    <ul className="flex flex-wrap gap-x-4 gap-y-1">
                        {tools.map(tool => (
                            <li key={tool.id}>
                                <a
                                    href={tool.href}
                                    target={target}
                                    data-testid={`tool-${tool.id}`}
                                    className="text-sm text-primary underline-offset-4 hover:underline"
                                >
                                    {toolLabels[tool.id]}
                                </a>
                            </li>
                        ))}
                    </ul>
                </section>
            ) : null}
        </div>
    );
}
