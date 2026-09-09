'use client';

import {PropsWithChildren, Suspense, useState} from 'react';
import {QueryClient, QueryClientProvider} from '@tanstack/react-query';
import {ThemeProvider} from 'next-themes';
import {Toaster} from 'sonner';
import {Tooltip} from 'radix-ui';
import {ConfigProvider} from '@/lib/config/ConfigProvider';
import type {AppConfig} from '@/lib/config/types';
import {AuthProvider} from '@/lib/auth/AuthProvider';
import {I18nProvider} from '@/i18n/I18nProvider';
import type {LanguageCode} from '@/i18n/config';
import {isApiError} from '@/lib/api/http';
import {FullPageLoader} from '@/components/ui/loader';
import {UserPreferencesGate} from '@/features/preferences/UserPreferencesGate';
import {RealtimeProvider} from '@/lib/realtime/RealtimeProvider';
import {ModalProvider} from '@/components/modals/ModalProvider';
import {SessionExpiredDialog} from '@/lib/auth/SessionExpiredDialog';

function createQueryClient(): QueryClient {
    return new QueryClient({
        defaultOptions: {
            queries: {
                staleTime: 5 * 60 * 1000,
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
                    <QueryClientProvider client={queryClient}>
                        <Suspense fallback={<FullPageLoader />}>
                            <AuthProvider>
                                <Tooltip.Provider delayDuration={300}>
                                    <RealtimeProvider>
                                        <UserPreferencesGate>
                                            <ModalProvider>
                                                {children}
                                            </ModalProvider>
                                        </UserPreferencesGate>
                                    </RealtimeProvider>
                                    <SessionExpiredDialog />
                                </Tooltip.Provider>
                            </AuthProvider>
                        </Suspense>
                        <Toaster
                            position="bottom-left"
                            richColors
                            closeButton
                            toastOptions={{duration: 5000}}
                        />
                    </QueryClientProvider>
                </ThemeProvider>
            </I18nProvider>
        </ConfigProvider>
    );
}
