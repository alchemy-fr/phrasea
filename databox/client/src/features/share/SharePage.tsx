'use client';

import {useCallback, useEffect, useMemo, useRef, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {useQuery} from '@tanstack/react-query';
import {useSearchParams} from 'next/navigation';
import {DownloadIcon, ImagesIcon} from 'lucide-react';
import {getPublicShare} from '@/lib/api/misc';
import {FullPageLoader} from '@/components/ui/loader';
import {EmptyState, Separator} from '@/components/ui/misc';
import {Button} from '@/components/ui/button';
import {TopBar} from '@/components/layout/TopBar';
import {belowTopBar} from '@/components/layout/chrome';
import {useModals} from '@/components/modals/ModalProvider';
import {FilePlayer} from '@/features/assets/player/FilePlayer';
import {cn} from '@/lib/utils/cn';
import type {Asset, Share} from '@/types/api';
import {ShareAssetView} from './ShareAssetView';
import {ShareAttachments} from './ShareAttachments';
import {ShareDownloadDialog} from './ShareDownloadDialog';
import {ShareGallery, ShareGalleryControls} from './ShareGallery';
import {
    hasAcceptedTerms,
    ShareTermsDialog,
    ShareTermsSection,
    storeAcceptedTerms,
} from './ShareTerms';
import {
    getDefaultViewRendition,
    getRenditionFile,
    getShareRenditions,
    type ShareRendition,
} from './shareRenditions';

/** Query parameter of the asset opened in the viewer of a multi-asset share */
const assetParam = 'asset';

/**
 * Public page of a share (`/s/:id/:token`), under the header of the
 * application (logo, settings, user menu) with the workspace's logo.
 *
 * A single asset is displayed right away (media, downloads, attributes); a
 * share of several assets opens on a gallery — grid, masonry or list — from
 * which each asset opens in a viewer (`?asset=<id>`, prev / next).
 *
 * When the workspace has Terms & Conditions, the visitor accepts them first
 * (remembered on this browser for the accepted version). Supports `?embed=1`
 * for iframe embedding: the players only.
 */
export function SharePage({id, token}: {id: string; token: string}) {
    const {t} = useTranslation();
    const searchParams = useSearchParams();
    const embed = searchParams.get('embed') === '1';
    const share = useQuery({
        queryKey: ['public-share', id, token],
        queryFn: () => getPublicShare(id, token),
    });
    const terms = share.data?.terms ?? null;
    const [termsState, setTermsState] = useState<
        'pending' | 'required' | 'accepted'
    >('pending');

    // Checked after mount: browser storage is not available server side
    useEffect(() => {
        if (share.data) {
            setTermsState(
                !terms || hasAcceptedTerms(share.data.id, terms)
                    ? 'accepted'
                    : 'required'
            );
        }
    }, [share.data, terms]);

    if (share.isLoading) {
        return <FullPageLoader />;
    }
    const assets = share.data?.assets ?? [];
    if (share.isError || !share.data || assets.length === 0) {
        return (
            <PublicLayout>
                <EmptyState
                    className="flex-1"
                    icon={<ImagesIcon />}
                    title={t(
                        'share.not_found',
                        'This link is not valid or has expired'
                    )}
                />
            </PublicLayout>
        );
    }
    const data = share.data;

    if (termsState !== 'accepted') {
        return terms && termsState === 'required' ? (
            <PublicLayout logo={data.logo}>
                <ShareTermsDialog
                    terms={terms}
                    onAccept={() => {
                        storeAcceptedTerms(data.id, terms);
                        setTermsState('accepted');
                    }}
                />
            </PublicLayout>
        ) : (
            <FullPageLoader />
        );
    }

    if (embed) {
        return <ShareEmbed share={data} />;
    }

    return (
        <PublicLayout logo={data.logo}>
            <ShareContent share={data} />
        </PublicLayout>
    );
}

/** The application header over the page, with the workspace's logo */
function PublicLayout({
    logo,
    children,
}: {
    logo?: string | null;
    children: React.ReactNode;
}) {
    return (
        <div className="flex h-[100dvh] w-full flex-col overflow-hidden bg-background">
            <TopBar variant="public">
                {logo ? (
                    <>
                        <Separator orientation="vertical" className="!h-6" />
                        {/* eslint-disable-next-line @next/next/no-img-element */}
                        <img
                            data-testid="share-logo"
                            src={logo}
                            alt=""
                            className="max-h-8 max-w-48 object-contain"
                        />
                    </>
                ) : null}
            </TopBar>
            <div className="relative flex min-h-0 flex-1 flex-col">
                {children}
            </div>
        </div>
    );
}

function ShareContent({share}: {share: Share}) {
    const {t} = useTranslation();
    const {openModal} = useModals();
    const searchParams = useSearchParams();
    const assets = share.assets;
    const single = assets.length === 1;
    const labels = useFallbackLabels();
    const renditions = useMemo(() => {
        const byAsset: Record<string, ShareRendition[]> = {};
        assets.forEach(a => {
            byAsset[a.id] = getShareRenditions(share, a, labels);
        });

        return byAsset;
    }, [share, assets, labels]);

    const download = useCallback(
        (list: Asset[]) =>
            openModal(ShareDownloadDialog, {
                items: list.map(asset => ({
                    asset,
                    renditions: renditions[asset.id] ?? [],
                })),
            }),
        [openModal, renditions]
    );

    // The viewer: `?asset=` in the URL, so that it can be linked to and the
    // Back button closes it
    const openedId = single ? undefined : searchParams.get(assetParam);
    const openedIndex = openedId
        ? assets.findIndex(a => a.id === openedId)
        : -1;
    const pushedRef = useRef(false);
    const setOpened = useCallback(
        (assetId: string | undefined, mode: 'push' | 'replace') => {
            const url = new URL(window.location.href);
            if (assetId) {
                url.searchParams.set(assetParam, assetId);
            } else {
                url.searchParams.delete(assetParam);
            }
            if (mode === 'push') {
                pushedRef.current = true;
                window.history.pushState(null, '', url);
            } else {
                window.history.replaceState(null, '', url);
            }
        },
        []
    );
    const navigation = useMemo(() => {
        if (openedIndex < 0) {
            return undefined;
        }
        const prev = assets[openedIndex - 1];
        const next = assets[openedIndex + 1];

        return {
            index: openedIndex,
            total: assets.length,
            onPrev: prev ? () => setOpened(prev.id, 'replace') : undefined,
            onNext: next ? () => setOpened(next.id, 'replace') : undefined,
            onClose: () => {
                if (pushedRef.current) {
                    pushedRef.current = false;
                    window.history.back();
                } else {
                    setOpened(undefined, 'replace');
                }
            },
        };
    }, [assets, openedIndex, setOpened]);

    if (single) {
        const asset = assets[0];

        return (
            <ShareAssetView
                share={share}
                asset={asset}
                renditions={renditions[asset.id] ?? []}
                onDownload={() => download([asset])}
                footer={
                    share.terms ? (
                        <ShareTermsSection terms={share.terms} />
                    ) : undefined
                }
            />
        );
    }

    return (
        <>
            <main
                className="flex-1 overflow-y-auto"
                // The gallery stays mounted under the viewer: its scroll
                // position is kept when the viewer is closed
                aria-hidden={navigation ? true : undefined}
            >
                <div className="mx-auto flex w-full max-w-[1600px] flex-col gap-6 p-4 sm:p-6">
                    <div className="flex flex-wrap items-center gap-3">
                        <h1 className="text-lg font-semibold">
                            {t('share.page.title', '{{count}} shared assets', {
                                count: assets.length,
                            })}
                        </h1>
                        <div className="flex-1" />
                        <ShareGalleryControls />
                        <Button
                            data-testid="share-download-all"
                            onClick={() => download(assets)}
                        >
                            <DownloadIcon />
                            {t('share.download.all', 'Download all…')}
                        </Button>
                    </div>
                    <ShareGallery
                        assets={assets}
                        onOpen={asset => setOpened(asset.id, 'push')}
                        onDownload={asset => download([asset])}
                    />
                    {share.attachments?.length || share.terms ? (
                        <div className="grid gap-6 border-t pt-6 md:grid-cols-2">
                            {share.attachments?.length ? (
                                <ShareAttachments
                                    attachments={share.attachments}
                                />
                            ) : null}
                            {share.terms ? (
                                <ShareTermsSection terms={share.terms} />
                            ) : null}
                        </div>
                    ) : null}
                </div>
            </main>
            {navigation ? (
                <div
                    className={cn(
                        belowTopBar,
                        'z-30 flex flex-col bg-background'
                    )}
                >
                    <ShareAssetView
                        share={share}
                        asset={assets[navigation.index]}
                        renditions={
                            renditions[assets[navigation.index].id] ?? []
                        }
                        onDownload={() => download([assets[navigation.index]])}
                        navigation={navigation}
                    />
                </div>
            ) : null}
        </>
    );
}

/** `?embed=1`: the players only, for an iframe */
function ShareEmbed({share}: {share: Share}) {
    const labels = useFallbackLabels();

    return (
        <div className="flex h-full flex-col gap-2 overflow-y-auto bg-media-bg">
            {share.assets.map(asset => {
                const rendition = getDefaultViewRendition(
                    asset,
                    getShareRenditions(share, asset, labels)
                );

                return (
                    <div
                        key={asset.id}
                        className="flex min-h-full flex-1 items-center justify-center"
                    >
                        {rendition ? (
                            <FilePlayer
                                file={getRenditionFile(asset, rendition)}
                                title={asset.name}
                                autoPlay
                                className="max-h-[80vh]"
                            />
                        ) : null}
                    </div>
                );
            })}
        </div>
    );
}

/** Names of the renditions embedded in the assets (see `getShareRenditions`) */
function useFallbackLabels() {
    const {t} = useTranslation();

    return useMemo(
        () => ({
            preview: t('share.rendition.preview', 'Preview'),
            main: t('share.rendition.main', 'Main'),
            source: t('share.rendition.source', 'Source'),
        }),
        [t]
    );
}
