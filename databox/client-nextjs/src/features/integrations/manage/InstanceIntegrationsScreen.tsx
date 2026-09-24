'use client';

import {useTranslation} from 'react-i18next';
import {PlugIcon} from 'lucide-react';
import {RequireAuth} from '@/lib/auth/RequireAuth';
import {AppRole, useAuth} from '@/lib/auth/AuthProvider';
import {EmptyState} from '@/components/ui/misc';
import {IntegrationManager} from './IntegrationManager';

/**
 * Integrations set up on the whole instance rather than on a workspace
 * (e.g. Expose), managed by the administrators.
 */
export function InstanceIntegrationsScreen() {
    const {t} = useTranslation();
    const {hasRole} = useAuth();
    const isAdmin = hasRole(AppRole.DataboxAdmin) || hasRole(AppRole.Admin);

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
                        <div>
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
                    </div>
                    <IntegrationManager fill />
                </div>
            )}
        </RequireAuth>
    );
}
