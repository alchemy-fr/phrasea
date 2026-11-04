'use client';

import {PropsWithChildren, Suspense, useState} from 'react';
import {QueryClient, QueryClientProvider} from '@tanstack/react-query';
import {ThemeProvider} from 'next-themes';
import {Tooltip} from 'radix-ui';
import {ConfigProvider} from '@/lib/config/ConfigProvider';
import type {AppConfig} from '@/lib/config/types';
import {AuthProvider} from '@/lib/auth/AuthProvider';
import {I18nProvider} from '@/i18n/I18nProvider';
import type {LanguageCode} from '@/i18n/config';
import {isApiError} from '@/lib/api/http';
import {FullPageLoader} from '@alchemy/next-ui/ui/loader';
import {ModalProvider} from '@alchemy/next-ui/modals/ModalProvider';
import {SessionExpiredDialog} from '@/lib/auth/SessionExpiredDialog';
import {ThemeManager} from '@alchemy/next-theme/ThemeManager';
import {UnsavedChangesGuard} from '@alchemy/next-ui/modals/UnsavedChangesGuard';
import {Toaster} from '@alchemy/next-ui/ui/toaster';
import {MatomoTracker} from '@/lib/analytics/MatomoTracker';

function createQueryClient(): QueryClient {
    return new QueryClient({
        defaultOptions: {
            queries: {
                staleTime: 60 * 1000,
                refetchOnWindowFocus: false,
                retry: (failureCount, error) => {
                    if (isApiError(error) && error.status < 500) {
                        return false;
                    }

                    return failureCount < 2;
                },
            },
        },
    });
}

type Props = PropsWithChildren<{
    config: AppConfig;
    language: LanguageCode;
}>;

export function Providers({config, language, children}: Props) {
    const [queryClient] = useState(createQueryClient);

    return (
        <ConfigProvider config={config}>
            <I18nProvider language={language}>
                <ThemeProvider
                    attribute="class"
                    defaultTheme="system"
                    enableSystem
                    disableTransitionOnChange
                >
                    <ThemeManager meta={config.theme} />
                    <QueryClientProvider client={queryClient}>
                        <Suspense fallback={<FullPageLoader />}>
                            <AuthProvider>
                                <MatomoTracker />
                                <Tooltip.Provider delayDuration={300}>
                                    <ModalProvider>{children}</ModalProvider>
                                    <SessionExpiredDialog />
                                    <UnsavedChangesGuard />
                                </Tooltip.Provider>
                            </AuthProvider>
                        </Suspense>
                        <Toaster />
                    </QueryClientProvider>
                </ThemeProvider>
            </I18nProvider>
        </ConfigProvider>
    );
}
