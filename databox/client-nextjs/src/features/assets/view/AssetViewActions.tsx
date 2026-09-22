'use client';

import {Fragment} from 'react';
import {useTranslation} from 'react-i18next';
import {PanelRightCloseIcon, PanelRightOpenIcon} from 'lucide-react';
import type {Asset, AssetRendition} from '@/types/api';
import {EntityName} from '@/types/api';
import {Button} from '@/components/ui/button';
import {Tooltip} from '@/components/ui/overlays';
import {useAssetActions} from '@/features/assets/actions/useAssetActions';
import {FollowButton} from '@/components/FollowButton';
import {useAuth} from '@/lib/auth/AuthProvider';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/menu';
import {MoreHorizontalIcon} from 'lucide-react';
import {AssetMenuItems} from '@/features/assets/list/AssetContextMenu';

export function AssetViewActions({
    asset,
    rendition,
    onTogglePanel,
    onEdit,
    editing,
    panelOpen,
}: {
    asset: Asset;
    rendition?: AssetRendition;
    onTogglePanel: () => void;
    /** Turns the edit mode of the side panel on and off */
    onEdit: () => void;
    editing: boolean;
    panelOpen: boolean;
}) {
    const {t} = useTranslation();
    const {isAuthenticated} = useAuth();
    const groups = useAssetActions([asset], {
        context: {open: false, info: false, basket: true},
    });
    // Editing happens in the side panel, next to the media
    const primary = groups
        .flat()
        .filter(a =>
            ['download', 'edit', 'share', 'delete', 'restore'].includes(a.id)
        )
        .map(a => (a.id === 'edit' ? {...a, run: onEdit} : a));
    void rendition;

    return (
        <div className="flex items-center gap-1">
            {isAuthenticated ? (
                <FollowButton
                    entity={EntityName.Asset}
                    id={asset.id}
                    subscriptions={asset.topicSubscriptions ?? []}
                    topics={[
                        {
                            key: 'asset:update',
                            label: t('asset.follow.update', 'Updates'),
                        },
                        {
                            key: 'asset:delete',
                            label: t('asset.follow.delete', 'Deletion'),
                        },
                        {
                            key: 'asset:new_comment',
                            label: t(
                                'asset.follow.new_comment',
                                'New comments'
                            ),
                        },
                    ]}
                />
            ) : null}
            {primary.map(a => (
                <Fragment key={a.id}>
                    <Tooltip content={a.label}>
                        <Button
                            variant={
                                a.id === 'edit' && editing
                                    ? 'secondary'
                                    : 'ghost'
                            }
                            size="icon-sm"
                            data-testid={`asset-action-${a.id}`}
                            onClick={() => a.run()}
                            disabled={a.disabled}
                            aria-pressed={a.id === 'edit' ? editing : undefined}
                            className={
                                a.destructive ? 'text-destructive' : undefined
                            }
                            aria-label={String(a.label)}
                        >
                            {a.icon}
                        </Button>
                    </Tooltip>
                </Fragment>
            ))}
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button
                        variant="ghost"
                        size="icon-sm"
                        aria-label={t('common.more', 'More')}
                    >
                        <MoreHorizontalIcon />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" className="w-56">
                    <AssetMenuItems
                        asset={asset}
                        variant="dropdown"
                        context={{open: false, info: false, edit: false}}
                    />
                </DropdownMenuContent>
            </DropdownMenu>
            <Tooltip
                content={
                    panelOpen
                        ? t('asset.view.hide_panel', 'Hide panel')
                        : t('asset.view.show_panel', 'Show panel')
                }
            >
                <Button
                    variant="ghost"
                    size="icon-sm"
                    onClick={onTogglePanel}
                    aria-label={
                        panelOpen
                            ? t('asset.view.hide_panel', 'Hide panel')
                            : t('asset.view.show_panel', 'Show panel')
                    }
                >
                    {panelOpen ? (
                        <PanelRightCloseIcon />
                    ) : (
                        <PanelRightOpenIcon />
                    )}
                </Button>
            </Tooltip>
        </div>
    );
}
