'use client';

import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {KeyRoundIcon, PlugIcon} from 'lucide-react';
import {usePathname} from 'next/navigation';
import {RequireAuth} from '@/lib/auth/RequireAuth';
import {AppRole, useAuth} from '@/lib/auth/AuthProvider';
import {
    EmptyState,
    Tabs,
    TabsList,
    TabsTrigger,
} from '@alchemy/next-ui/ui/misc';
import {UrlPathProvider} from '@/lib/navigation/routePath';
import {routes} from '@/lib/routes';
import {usePageTrail} from '@/components/layout/layoutStore';
import {SecretManager} from '@/features/secrets/SecretManager';
import {IntegrationManager} from './IntegrationManager';

/**
 * Integrations set up on the whole instance rather than on a workspace
 * (e.g. Expose), managed by the administrators.
 */
export function InstanceIntegrationsScreen() {
    const {t} = useTranslation();
    const {hasRole} = useAuth();
    const isAdmin = hasRole(AppRole.DataboxAdmin) || hasRole(AppRole.Admin);
    const pathname = usePathname();
    const [section, setSection] = useState<'integrations' | 'secrets'>(
        'integrations'
    );

    // Where the user is, shown in the top bar with the way back to the assets
    usePageTrail(
        t('integration.instance.title', 'Instance integrations'),
        routes.instanceIntegrations()
    );

    return (
        <RequireAuth>
            {!isAdmin ? (
                <EmptyState
                    className="h-full"
                    title={t(
                        'common.forbidden',
                        'You are not allowed to access this page'
                    )}
                />
            ) : (
                <div
                    className="flex min-h-0 w-full flex-1 flex-col gap-4 overflow-x-hidden overflow-y-auto p-4 lg:overflow-y-hidden"
                    data-testid="instance-integrations"
                >
                    <div className="flex shrink-0 items-start gap-2">
                        <PlugIcon className="mt-0.5 size-5 text-muted-foreground" />
                        <div className="min-w-0 flex-1">
                            <h1 className="text-lg font-semibold">
                                {t(
                                    'integration.instance.title',
                                    'Instance integrations'
                                )}
                            </h1>
                            <p className="text-sm text-muted-foreground">
                                {t(
                                    'integration.instance.intro',
                                    'These integrations are not attached to a workspace: they are available everywhere on this instance.'
                                )}
                            </p>
                        </div>
                        <Tabs
                            value={section}
                            onValueChange={v =>
                                setSection(v as 'integrations' | 'secrets')
                            }
                        >
                            <TabsList>
                                <TabsTrigger
                                    value="integrations"
                                    data-testid="instance-integrations-tab"
                                >
                                    <PlugIcon />
                                    {t(
                                        'integration.instance.integrations',
                                        'Integrations'
                                    )}
                                </TabsTrigger>
                                <TabsTrigger
                                    value="secrets"
                                    data-testid="instance-secrets-tab"
                                >
                                    <KeyRoundIcon />
                                    {t(
                                        'integration.instance.secrets',
                                        'Instance secrets & variables'
                                    )}
                                </TabsTrigger>
                            </TabsList>
                        </Tabs>
                    </div>
                    {section === 'secrets' ? (
                        <div className="min-h-0 flex-1 overflow-y-auto">
                            <SecretManager />
                        </div>
                    ) : (
                        // `/admin/integrations/:id`: the integration edited
                        <UrlPathProvider
                            base={routes.instanceIntegrations()}
                            pathname={pathname}
                        >
                            <IntegrationManager fill />
                        </UrlPathProvider>
                    )}
                </div>
            )}
        </RequireAuth>
    );
}
