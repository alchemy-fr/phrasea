'use client';

import {
    ComponentType,
    memo,
    ReactNode,
    useEffect,
    useMemo,
    useRef,
    useState,
} from 'react';
import {useTranslation} from 'react-i18next';
import {
    DatabaseIcon,
    HistoryIcon,
    ImagesIcon,
    InfoIcon,
    MoreHorizontalIcon,
    PencilIcon,
    ShieldIcon,
    WorkflowIcon,
    WrenchIcon,
    XIcon,
} from 'lucide-react';
import type {Asset, AssetRendition} from '@/types/api';
import {AppRole, useAuth} from '@/lib/auth/AuthProvider';
import {
    Tabs,
    UnderlineTabsList,
    UnderlineTabsTrigger,
} from '@/components/ui/misc';
import {Button} from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/menu';
import {Tooltip} from '@/components/ui/overlays';
import type {AssetTabProps} from '@/features/assets/manage/types';
import {AssetEditTab} from '@/features/assets/manage/tabs/AssetEditTab';
import {AssetRenditionsTab} from '@/features/assets/manage/tabs/AssetRenditionsTab';
import {AssetVersionsTab} from '@/features/assets/manage/tabs/AssetVersionsTab';
import {AssetPermissionsTab} from '@/features/assets/manage/tabs/AssetPermissionsTab';
import {AssetWorkflowTab} from '@/features/assets/manage/tabs/AssetWorkflowTab';
import {AssetOperationsTab} from '@/features/assets/manage/tabs/AssetOperationsTab';
import {ESDocumentTab} from '@/features/assets/manage/tabs/ESDocumentTab';
import {cn} from '@/lib/utils/cn';
import {AssetSidePanel} from './AssetSidePanel';

/** Every tab of the asset side panel. `details` is the default one. */
export const assetPanelTabs = [
    'details',
    'renditions',
    'versions',
    'permissions',
    'workflow',
    'operations',
    'es',
] as const;

export type AssetPanelTab = (typeof assetPanelTabs)[number];

/** Editing is a mode of the panel, turned on from the viewer, not a tab. */
export const editPanelTarget = 'edit';

export type AssetPanelTarget = AssetPanelTab | typeof editPanelTarget;

export const defaultAssetPanelTab: AssetPanelTab = 'details';

/**
 * Resolves a panel target coming from the outside (URL hash, action) to a tab
 * or to the edit mode. `info` and `open` are tabs of the former manage dialog
 * whose content now lives in `details`.
 */
export function resolveAssetPanelTarget(
    name: string | null | undefined
): AssetPanelTarget {
    if (name === editPanelTarget) {
        return editPanelTarget;
    }
    if (name === 'info' || name === 'open') {
        return defaultAssetPanelTab;
    }

    return (assetPanelTabs as readonly string[]).includes(name ?? '')
        ? (name as AssetPanelTab)
        : defaultAssetPanelTab;
}

type PanelTab = {
    id: AssetPanelTab;
    title: string;
    icon: ReactNode;
    component: ComponentType<any>;
    props?: Record<string, unknown>;
    enabled?: boolean;
};

function useTabs(asset: Asset, rendition?: AssetRendition): PanelTab[] {
    const {t} = useTranslation();
    const {hasRole} = useAuth();

    return [
        {
            id: 'details',
            title: t('asset.manage.info', 'Info'),
            icon: <InfoIcon />,
            component: AssetSidePanel,
            props: {rendition},
        },
        {
            id: 'renditions',
            title: t('asset.manage.renditions', 'Renditions'),
            icon: <ImagesIcon />,
            component: AssetRenditionsTab,
        },
        {
            id: 'versions',
            title: t('asset.manage.versions', 'Versions'),
            icon: <HistoryIcon />,
            component: AssetVersionsTab,
            enabled: !!asset.capabilities.edit,
        },
        {
            id: 'permissions',
            title: t('asset.manage.permissions', 'Permissions'),
            icon: <ShieldIcon />,
            component: AssetPermissionsTab,
            enabled: !!asset.capabilities.editPermissions,
        },
        {
            id: 'workflow',
            title: t('asset.manage.workflow', 'Workflow'),
            icon: <WorkflowIcon />,
            component: AssetWorkflowTab,
            enabled: !!asset.capabilities.edit,
        },
        {
            id: 'operations',
            title: t('asset.manage.operations', 'Operations'),
            icon: <WrenchIcon />,
            component: AssetOperationsTab,
            enabled: !!(asset.capabilities.edit || asset.capabilities.delete),
        },
        {
            id: 'es',
            title: t('asset.manage.es_document', 'ES Document'),
            icon: <DatabaseIcon />,
            component: ESDocumentTab,
            enabled: hasRole(AppRole.Tech),
        },
    ];
}

/** Room kept for the “…” button, and the gap between two triggers */
const moreButtonWidth = 40;
const triggerGap = 4;

/**
 * Keeps the tab row on a single line: the natural width of every trigger is
 * measured once (all of them rendered, invisible), then only those that fit
 * the panel are shown — the others go to the “…” menu. The active tab is
 * always one of the visible ones.
 */
function useSingleLineTabs(tabs: PanelTab[], active: AssetPanelTab) {
    const {i18n} = useTranslation();
    const rowRef = useRef<HTMLDivElement>(null);
    const [widths, setWidths] = useState<Record<string, number>>({});
    const [rowWidth, setRowWidth] = useState(0);

    // Labels — hence widths — change with the language
    useEffect(() => setWidths({}), [i18n.language]);

    const measuring = tabs.some(tb => !widths[tb.id]);

    // Runs again when the row is resized: while the panel is hidden every
    // width is zero, and the tabs are measured when it comes back.
    useEffect(() => {
        const row = rowRef.current;
        if (!row || !measuring) {
            return;
        }
        const measured: Record<string, number> = {};
        row.querySelectorAll<HTMLElement>('[data-tab-id]').forEach(el => {
            const id = el.dataset.tabId;
            if (id && el.offsetWidth > 0 && !widths[id]) {
                measured[id] = el.offsetWidth;
            }
        });
        if (Object.keys(measured).length > 0) {
            setWidths(w => ({...w, ...measured}));
        }
    }, [measuring, widths, rowWidth]);

    useEffect(() => {
        const row = rowRef.current;
        if (!row || typeof ResizeObserver === 'undefined') {
            return;
        }
        const observer = new ResizeObserver(entries =>
            setRowWidth(entries[0].contentRect.width)
        );
        observer.observe(row);

        return () => observer.disconnect();
    }, []);

    const {visible, hidden} = useMemo(() => {
        if (measuring || rowWidth === 0) {
            return {visible: tabs, hidden: [] as PanelTab[]};
        }
        const width = (tb: PanelTab) => widths[tb.id] + triggerGap;
        const activeIndex = Math.max(
            0,
            tabs.findIndex(tb => tb.id === active)
        );

        for (let count = tabs.length; count > 1; count--) {
            // The active tab takes the last slot when it does not fit in
            const shown =
                activeIndex < count
                    ? tabs.slice(0, count)
                    : [...tabs.slice(0, count - 1), tabs[activeIndex]];
            const used =
                shown.reduce((total, tb) => total + width(tb), 0) +
                (count < tabs.length ? moreButtonWidth : 0);
            if (used <= rowWidth) {
                return {
                    visible: shown,
                    hidden: tabs.filter(tb => !shown.includes(tb)),
                };
            }
        }
        const shown = [tabs[activeIndex]];

        return {visible: shown, hidden: tabs.filter(tb => tb !== shown[0])};
    }, [tabs, active, widths, rowWidth, measuring]);

    return {rowRef, measuring, visible, hidden};
}

/**
 * Side panel of the asset view: everything about the displayed asset, from
 * its attributes and its discussion (`details`) to the tabs that used to live
 * in a separate manage dialog (renditions, versions, …).
 *
 * Editing is a mode rather than a tab: it is turned on from the viewer's
 * toolbar and takes the panel over until it is closed. Visited tabs — and the
 * editor once opened — stay mounted, so coming back to one keeps its state,
 * unsaved edits included.
 */
export function AssetPanel({
    asset,
    rendition,
    tab,
    onTabChange,
    editing,
    onExitEdit,
    refresh,
}: {
    asset: Asset;
    rendition?: AssetRendition;
    tab: AssetPanelTab;
    onTabChange: (tab: AssetPanelTab) => void;
    editing: boolean;
    onExitEdit: () => void;
    refresh: () => void;
}) {
    const {t} = useTranslation();
    const tabs = useTabs(asset, rendition).filter(tb => tb.enabled !== false);
    const active = tabs.find(tb => tb.id === tab) ? tab : defaultAssetPanelTab;
    const {rowRef, measuring, visible, hidden} = useSingleLineTabs(
        tabs,
        active
    );

    const [visited, setVisited] = useState<AssetPanelTab[]>([]);
    if (!visited.includes(active)) {
        setVisited([...visited, active]);
    }
    // The editor is mounted on first use and kept afterwards: leaving the
    // edit mode must not throw unsaved changes away
    const [editorUsed, setEditorUsed] = useState(false);
    if (editing && !editorUsed) {
        setEditorUsed(true);
    }

    // Stable for the memoized tab contents
    const baseProps = useMemo<AssetTabProps>(
        () => ({asset, refresh}),
        [asset, refresh]
    );

    return (
        <div className="flex h-full min-h-0 flex-col">
            {editing ? (
                <div className="flex h-10 shrink-0 items-center gap-2 border-b px-3">
                    <PencilIcon className="size-4 text-muted-foreground" />
                    <span className="flex-1 text-sm font-medium">
                        {t('asset.manage.edit', 'Edit')}
                    </span>
                    <Tooltip content={t('asset.edit.close', 'Close editor')}>
                        <Button
                            variant="ghost"
                            size="icon-sm"
                            data-testid="asset-panel-edit-close"
                            onClick={onExitEdit}
                            aria-label={t('asset.edit.close', 'Close editor')}
                        >
                            <XIcon />
                        </Button>
                    </Tooltip>
                </div>
            ) : (
                <Tabs
                    value={active}
                    onValueChange={value => onTabChange(value as AssetPanelTab)}
                >
                    <div
                        ref={rowRef}
                        className={cn(
                            'flex items-center gap-1 border-b px-2',
                            measuring && 'invisible'
                        )}
                    >
                        <UnderlineTabsList className="w-auto min-w-0 flex-1 flex-nowrap overflow-x-hidden border-b-0">
                            {visible.map(tb => (
                                <UnderlineTabsTrigger
                                    key={tb.id}
                                    value={tb.id}
                                    data-tab-id={tb.id}
                                    data-testid={`asset-panel-trigger-${tb.id}`}
                                >
                                    {tb.icon} {tb.title}
                                </UnderlineTabsTrigger>
                            ))}
                        </UnderlineTabsList>
                        {hidden.length > 0 ? (
                            // Not modal: switching tab from here leaves the
                            // panel interactive right away
                            <DropdownMenu modal={false}>
                                <DropdownMenuTrigger asChild>
                                    <Button
                                        variant="ghost"
                                        size="icon-sm"
                                        data-testid="asset-panel-more"
                                        aria-label={t('common.more', 'More')}
                                    >
                                        <MoreHorizontalIcon />
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end">
                                    {hidden.map(tb => (
                                        <DropdownMenuItem
                                            key={tb.id}
                                            onSelect={() => onTabChange(tb.id)}
                                        >
                                            {tb.icon} {tb.title}
                                        </DropdownMenuItem>
                                    ))}
                                </DropdownMenuContent>
                            </DropdownMenu>
                        ) : null}
                    </div>
                </Tabs>
            )}
            <div className="min-h-0 flex-1 overflow-y-auto px-4 py-2">
                <div hidden={editing}>
                    {tabs
                        .filter(tb => visited.includes(tb.id))
                        .map(tb => (
                            <div
                                key={tb.id}
                                hidden={tb.id !== active}
                                data-testid={`asset-panel-tab-${tb.id}`}
                            >
                                <TabContent
                                    component={tb.component}
                                    baseProps={baseProps}
                                    tabProps={tb.props}
                                />
                            </div>
                        ))}
                </div>
                {editorUsed ? (
                    <div hidden={!editing} data-testid="asset-panel-edit">
                        <AssetEditTab {...baseProps} />
                    </div>
                ) : null}
            </div>
        </div>
    );
}

/**
 * Memoized: hidden tabs stay mounted and must not re-render on every switch.
 */
const TabContent = memo(function TabContent({
    component: Component,
    baseProps,
    tabProps,
}: {
    component: ComponentType<any>;
    baseProps: AssetTabProps;
    tabProps?: Record<string, unknown>;
}) {
    return <Component {...baseProps} {...(tabProps ?? {})} />;
});
