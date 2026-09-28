'use client';

import {useEffect, useMemo, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {useQuery} from '@tanstack/react-query';
import {useSearchParams} from 'next/navigation';
import {
    DownloadIcon,
    FileTextIcon,
    ImagesIcon,
    PaperclipIcon,
} from 'lucide-react';
import {getPublicShare} from '@/lib/api/misc';
import {FullPageLoader} from '@/components/ui/loader';
import {EmptyState} from '@/components/ui/misc';
import {Button} from '@/components/ui/button';
import {Checkbox, LabeledControl} from '@/components/ui/controls';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {SimpleSelect} from '@/components/ui/select';
import {FilePlayer} from '@/features/assets/player/FilePlayer';
import {AttributeList} from '@/features/attributes/AttributeList';
import {formatFileSize} from '@/lib/utils/format';
import type {ApiFile, Asset, Share, ShareTerms} from '@/types/api';

/**
 * Public page of a share (`/s/:id/:token`): the logo of the workspace, then
 * every shared asset (player, renditions and attributes) and the attachments.
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
            <EmptyState
                className="h-full"
                icon={<ImagesIcon />}
                title={t(
                    'share.not_found',
                    'This link is not valid or has expired'
                )}
            />
        );
    }
    const data = share.data;

    if (termsState !== 'accepted') {
        return terms && termsState === 'required' ? (
            <ShareTermsDialog
                terms={terms}
                onAccept={() => {
                    storeAcceptedTerms(data.id, terms);
                    setTermsState('accepted');
                }}
            />
        ) : (
            <FullPageLoader />
        );
    }

    if (embed) {
        return (
            <div className="flex h-full flex-col gap-2 overflow-y-auto bg-media-bg">
                {assets.map(asset => (
                    <div
                        key={asset.id}
                        className="flex min-h-full flex-1 items-center justify-center"
                    >
                        <ShareAssetPlayer
                            share={data}
                            asset={asset}
                            single={assets.length === 1}
                            embed
                        />
                    </div>
                ))}
            </div>
        );
    }

    return (
        <div className="flex h-full flex-col overflow-y-auto bg-background">
            <header className="flex items-center justify-center border-b px-4 py-3">
                {data.logo ? (
                    // eslint-disable-next-line @next/next/no-img-element
                    <img src={data.logo} alt="" className="max-h-12 max-w-72" />
                ) : (
                    <ImagesIcon className="size-6 text-primary" />
                )}
            </header>
            <main className="mx-auto flex w-full max-w-7xl flex-col gap-8 p-4">
                {assets.map(asset => (
                    <ShareAssetPlayer
                        key={asset.id}
                        share={data}
                        asset={asset}
                        single={assets.length === 1}
                    />
                ))}
                {data.attachments?.length ? (
                    <ShareAttachments attachments={data.attachments} />
                ) : null}
                {terms ? <ShareTermsSection terms={terms} /> : null}
            </main>
        </div>
    );
}

function ShareAssetPlayer({
    share,
    asset,
    single,
    embed,
}: {
    share: Share;
    asset: Asset;
    /** The only asset of the share: its alternate URLs have no `assetId` */
    single: boolean;
    embed?: boolean;
}) {
    const {t} = useTranslation();
    const [renditionUrl, setRenditionUrl] = useState<string>();

    const files = useMemo(() => {
        const list: {label: string; file: ApiFile}[] = [];
        if (asset.preview?.file) {
            list.push({
                label: t('share.rendition.preview', 'Preview'),
                file: asset.preview.file,
            });
        }
        if (
            asset.main?.file &&
            asset.main.file.url !== asset.preview?.file?.url
        ) {
            list.push({
                label: t('share.rendition.main', 'Main'),
                file: asset.main.file,
            });
        }
        if (asset.source?.url) {
            list.push({
                label: t('share.rendition.source', 'Source'),
                file: asset.source,
            });
        }
        share.alternateUrls
            ?.filter(a => (a.assetId ? a.assetId === asset.id : single))
            .forEach(a =>
                list.push({
                    label: a.name,
                    file: {
                        id: a.url,
                        url: a.url,
                        type: a.type ?? '',
                        extension: '',
                        alternateUrls: [],
                        size: 0,
                        docUniqueId: '',
                        checksum: '',
                        fileName: a.name,
                        analysisPending: false,
                    },
                })
            );

        return list;
    }, [share, asset, single, t]);
    const current = files.find(f => f.file.url === renditionUrl) ?? files[0];

    const player = current ? (
        <FilePlayer
            file={current.file}
            title={asset.name}
            autoPlay={embed}
            className="max-h-[80vh]"
        />
    ) : null;
    if (embed) {
        return player;
    }

    return (
        <section data-testid="share-asset" className="flex flex-col gap-3">
            <div className="flex items-center gap-3">
                <h1 className="min-w-0 flex-1 truncate text-lg font-semibold">
                    {asset.name}
                </h1>
                {files.length > 1 ? (
                    <SimpleSelect
                        size="sm"
                        className="w-40"
                        value={current?.file.url}
                        onValueChange={setRenditionUrl}
                        options={files.map(f => ({
                            value: f.file.url!,
                            label: f.label,
                        }))}
                    />
                ) : null}
                {current?.file.url ? (
                    <Button size="sm" asChild>
                        <a
                            href={current.file.url}
                            download
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            <DownloadIcon />{' '}
                            {t('asset.actions.download', 'Download')}
                        </a>
                    </Button>
                ) : null}
            </div>
            <div className="grid gap-6 lg:grid-cols-[2fr_1fr]">
                <div className="flex min-h-[50vh] items-center justify-center rounded-lg bg-media-bg">
                    {player}
                </div>
                <aside>
                    <AttributeList asset={asset} />
                </aside>
            </div>
        </section>
    );
}

function ShareAttachments({
    attachments,
}: {
    attachments: NonNullable<Share['attachments']>;
}) {
    const {t, i18n} = useTranslation();

    return (
        <section data-testid="share-attachments">
            <h2 className="mb-2 text-base font-semibold">
                {t('share.attachments.title', 'Attached files')}
            </h2>
            <ul className="divide-y rounded-lg border">
                {attachments.map(a => (
                    <li key={a.id}>
                        <a
                            href={a.url}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="flex items-center gap-3 px-3 py-2 text-sm hover:bg-accent"
                        >
                            <PaperclipIcon className="size-4 shrink-0 text-muted-foreground" />
                            <span className="min-w-0 flex-1 truncate">
                                {a.name || t('share.attachments.file', 'File')}
                            </span>
                            {a.size ? (
                                <span className="text-xs text-muted-foreground">
                                    {formatFileSize(
                                        a.size,
                                        true,
                                        i18n.language
                                    )}
                                </span>
                            ) : null}
                        </a>
                    </li>
                ))}
            </ul>
        </section>
    );
}

function TermsContent({terms}: {terms: ShareTerms}) {
    const {t} = useTranslation();

    return terms.pdfUrl ? (
        <Button variant="outline" size="sm" asChild>
            <a href={terms.pdfUrl} target="_blank" rel="noopener noreferrer">
                <FileTextIcon />{' '}
                {t('share.terms.view_pdf', 'View Terms & Conditions (PDF)')}
            </a>
        </Button>
    ) : (
        <div className="max-h-80 overflow-y-auto rounded-md border p-3 text-sm whitespace-pre-wrap">
            {terms.text}
        </div>
    );
}

function ShareTermsDialog({
    terms,
    onAccept,
}: {
    terms: ShareTerms;
    onAccept: () => void;
}) {
    const {t} = useTranslation();
    const [accepted, setAccepted] = useState(false);

    return (
        <Dialog open>
            <DialogContent
                hideClose
                size="lg"
                data-testid="share-terms-dialog"
                onEscapeKeyDown={e => e.preventDefault()}
                onInteractOutside={e => e.preventDefault()}
            >
                <DialogHeader>
                    <DialogTitle>
                        {t('share.terms.title', 'Terms & Conditions')}
                    </DialogTitle>
                    <DialogDescription>
                        {t(
                            'share.terms.dialog_intro',
                            'You must accept the Terms & Conditions of {{workspace}} (version {{version}}) to access this content.',
                            {
                                workspace: terms.workspaceName,
                                version: terms.version,
                            }
                        )}
                    </DialogDescription>
                </DialogHeader>
                <TermsContent terms={terms} />
                <LabeledControl
                    label={t(
                        'share.terms.accept_label',
                        'I have read and accept the Terms & Conditions'
                    )}
                >
                    <Checkbox
                        checked={accepted}
                        onCheckedChange={v => setAccepted(v === true)}
                    />
                </LabeledControl>
                <DialogFooter>
                    <Button disabled={!accepted} onClick={onAccept}>
                        {t('share.terms.accept', 'Accept')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

function ShareTermsSection({terms}: {terms: ShareTerms}) {
    const {t} = useTranslation();

    return (
        <section className="text-sm text-muted-foreground">
            <h2 className="mb-2 text-base font-semibold text-foreground">
                {t('share.terms.title', 'Terms & Conditions')}
            </h2>
            <TermsContent terms={terms} />
        </section>
    );
}

const termsStorageKey = (shareId: string) => `share.terms.accepted.${shareId}`;

/** Whether this terms version was already accepted on this browser */
function hasAcceptedTerms(shareId: string, terms: ShareTerms): boolean {
    try {
        return (
            window.localStorage.getItem(termsStorageKey(shareId)) ===
            String(terms.version)
        );
    } catch {
        return false;
    }
}

function storeAcceptedTerms(shareId: string, terms: ShareTerms): void {
    try {
        window.localStorage.setItem(
            termsStorageKey(shareId),
            String(terms.version)
        );
    } catch {
        // Storage unavailable (private mode…): accepted for this page only
    }
}
