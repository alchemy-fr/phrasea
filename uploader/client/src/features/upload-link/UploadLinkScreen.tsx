'use client';

import {PropsWithChildren, useCallback, useMemo} from 'react';
import {useQuery, useQueryClient} from '@tanstack/react-query';
import {useTranslation} from 'react-i18next';
import {Link2OffIcon, UploadCloudIcon} from 'lucide-react';
import {
    AppLogo,
    LanguageMenu,
    SettingsMenu,
} from '@alchemy/next-ui/layout/menus';
import {EmptyState} from '@alchemy/next-ui/ui/misc';
import {FullPageLoader} from '@alchemy/next-ui/ui/loader';
import {getErrorMessage, isApiError} from '@alchemy/next-ui/utils/errors';
import {ThemeMenu} from '@alchemy/next-theme/ThemeMenu';
import {Page} from '@/components/layout/AppShell';
import {useConfig} from '@/lib/config/ConfigProvider';
import {languages} from '@/i18n';
import {
    getUploadLinkSession,
    uploadLinkRequestOptions,
    type UploadLinkErrorCode,
} from '@/lib/api/uploadLinks';
import {
    UploadAccessContext,
    type UploadAccess,
} from '@/features/upload/UploadAccessContext';
import {UploadScreen} from '@/features/upload/UploadScreen';

export const uploadLinkSessionQueryKey = (token: string) =>
    ['upload-link', token] as const;

/** The header of the people without an account: no navigation, no user menu */
function UploadLinkShell({
    title,
    children,
}: PropsWithChildren<{title?: string}>) {
    const config = useConfig();

    return (
        <div className="flex h-[100dvh] w-full flex-col overflow-hidden">
            <header
                data-testid="upload-link-topbar"
                className="flex h-12 shrink-0 items-center gap-2 bg-background px-2"
            >
                <AppLogo
                    name={title ?? 'Uploader'}
                    icon={<UploadCloudIcon />}
                    logo={config.logo}
                    nameClassName="truncate"
                />
                <div className="flex-1" />
                <SettingsMenu>
                    <ThemeMenu custom={config.theme} />
                    <LanguageMenu languages={languages.supportedLanguages} />
                </SettingsMenu>
            </header>
            <main className="relative min-h-0 flex-1 overflow-auto">
                {children}
            </main>
        </div>
    );
}

function errorCode(error: unknown): UploadLinkErrorCode | undefined {
    if (!isApiError(error, 401)) {
        return undefined;
    }
    const code = (error.data as {code?: unknown} | undefined)?.code;

    return code === 'upload_link_disabled' || code === 'upload_link_expired'
        ? code
        : 'upload_link_invalid';
}

function LinkError({error}: {error: unknown}) {
    const {t} = useTranslation();
    const code = errorCode(error);

    const title =
        code === 'upload_link_expired'
            ? t('upload_link.error.expired', 'This link has expired')
            : code === 'upload_link_disabled'
              ? t('upload_link.error.disabled', 'This link has been disabled')
              : code === 'upload_link_invalid'
                ? t('upload_link.error.invalid', 'This link is invalid')
                : getErrorMessage(error);

    return (
        <Page>
            <div data-testid="upload-link-error" data-code={code ?? 'other'}>
                <EmptyState
                    icon={<Link2OffIcon />}
                    title={title}
                    description={
                        code
                            ? t(
                                  'upload_link.error.help',
                                  'Ask the person who sent it to you for a new link.'
                              )
                            : undefined
                    }
                />
            </div>
        </Page>
    );
}

/**
 * The upload wizard of an upload link: sends files to the target of the link,
 * without an account, within what is left of its limits.
 */
export function UploadLinkScreen({token}: {token: string}) {
    const queryClient = useQueryClient();
    const queryKey = uploadLinkSessionQueryKey(token);
    const session = useQuery({
        queryKey,
        queryFn: () => getUploadLinkSession(token),
        staleTime: Infinity,
    });

    const requestOptions = useMemo(
        () => uploadLinkRequestOptions(token),
        [token]
    );
    const onCommit = useCallback(() => {
        void queryClient.invalidateQueries({
            queryKey: uploadLinkSessionQueryKey(token),
        });
    }, [queryClient, token]);
    const link = session.data;
    const access = useMemo<UploadAccess | undefined>(
        () =>
            link
                ? {
                      requestOptions,
                      link,
                      userKey: `link:${link.id}`,
                      onCommit,
                  }
                : undefined,
        [link, requestOptions, onCommit]
    );

    if (session.isPending) {
        return (
            <UploadLinkShell>
                <FullPageLoader />
            </UploadLinkShell>
        );
    }
    if (session.error || !access || !link) {
        return (
            <UploadLinkShell>
                <LinkError error={session.error} />
            </UploadLinkShell>
        );
    }

    return (
        <UploadLinkShell title={link.target.name}>
            <UploadAccessContext.Provider value={access}>
                <UploadScreen targetId={link.target.id} />
            </UploadAccessContext.Provider>
        </UploadLinkShell>
    );
}
