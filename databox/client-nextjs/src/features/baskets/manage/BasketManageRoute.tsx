'use client';

import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {useQuery} from '@tanstack/react-query';
import {
    InfoIcon,
    PencilIcon,
    PlugIcon,
    SaveIcon,
    ShieldIcon,
    Trash2Icon,
    WrenchIcon,
} from 'lucide-react';
import {toast} from 'sonner';
import type {Basket} from '@/types/api';
import {getBasket, putBasket} from '@/lib/api/misc';
import {
    TabbedRouteDialog,
    DialogTab,
} from '@/components/modals/TabbedRouteDialog';
import {RouteDialog} from '@/components/modals/RouteDialog';
import {FullPageLoader} from '@/components/ui/loader';
import {routes} from '@/lib/routes';
import {InfoRow} from '@/features/assets/view/AssetInfoList';
import {UserChip} from '@/components/chips';
import {formatDateTime} from '@/lib/utils/format';
import {Button} from '@/components/ui/button';
import {FormRow, Input, Textarea} from '@/components/ui/input';
import {Alert} from '@/components/ui/misc';
import {AclEditor} from '@/features/permissions/AclEditor';
import {genericPermissions} from '@/features/permissions/permissionDefinitions';
import {PermissionObject} from '@/features/permissions/permissionTypes';
import {useModals} from '@/components/modals/ModalProvider';
import {ConfirmDialog} from '@/components/ui/confirm';
import {useBasketStore} from '../basketStore';
import {BasketIntegrations} from './BasketIntegrations';

type TabProps = {basket: Basket; refresh: () => void; onClose: () => void};

export function BasketManageRoute({
    basketId,
    tab,
}: {
    basketId: string;
    tab: string;
}) {
    const {t} = useTranslation();
    const upsert = useBasketStore(s => s.upsert);
    const query = useQuery({
        queryKey: ['basket', basketId],
        queryFn: async () => {
            const b = await getBasket(basketId);
            upsert(b);

            return b;
        },
    });
    const basket = query.data;
    if (!basket) {
        return (
            <RouteDialog size="md">
                <FullPageLoader />
            </RouteDialog>
        );
    }

    const tabs: DialogTab<TabProps>[] = [
        {
            id: 'info',
            title: t('collection.manage.info', 'Info'),
            icon: <InfoIcon />,
            component: InfoTab,
        },
        {
            id: 'edit',
            title: t('common.edit', 'Edit'),
            icon: <PencilIcon />,
            component: EditTab,
            enabled: basket.capabilities.edit,
        },
        {
            id: 'permissions',
            title: t('collection.manage.permissions', 'Permissions'),
            icon: <ShieldIcon />,
            component: PermissionsTab,
            enabled: basket.capabilities.editPermissions,
        },
        {
            id: 'operations',
            title: t('collection.manage.operations', 'Operations'),
            icon: <WrenchIcon />,
            component: OperationsTab,
            enabled: basket.capabilities.delete,
        },
        {
            id: 'integrations',
            title: t('basket.integrations', 'Integrations'),
            icon: <PlugIcon />,
            component: ({basket: b}) => <BasketIntegrations basket={b} />,
        },
    ];

    return (
        <TabbedRouteDialog<TabProps>
            title={t('basket.manage.title', 'Manage basket')}
            subtitle={basket.name}
            tabs={tabs}
            activeTab={tab}
            buildTabHref={next => routes.basketManage(basketId, next)}
            baseProps={{
                basket,
                refresh: () => query.refetch(),
                onClose: () => undefined,
            }}
            size="md"
        />
    );
}

function InfoTab({basket}: TabProps) {
    const {t, i18n} = useTranslation();

    return (
        <dl className="divide-y">
            <InfoRow label="ID" copy={basket.id}>
                <code className="font-mono text-xs">{basket.id}</code>
            </InfoRow>
            {basket.owner ? (
                <InfoRow label={t('asset.info.owner', 'Owner')}>
                    <UserChip user={basket.owner} size="sm" />
                </InfoRow>
            ) : null}
            <InfoRow label={t('asset.info.created_at', 'Created at')}>
                {formatDateTime(basket.createdAt, 'medium', i18n.language)}
            </InfoRow>
            <InfoRow label={t('asset.info.updated_at', 'Updated at')}>
                {formatDateTime(basket.updatedAt, 'medium', i18n.language)}
            </InfoRow>
            {basket.description ? (
                <InfoRow label={t('common.description', 'Description')}>
                    {basket.description}
                </InfoRow>
            ) : null}
        </dl>
    );
}

function EditTab({basket, refresh}: TabProps) {
    const {t} = useTranslation();
    const upsert = useBasketStore(s => s.upsert);
    const [name, setName] = useState(basket.name);
    const [description, setDescription] = useState(basket.description ?? '');
    const [saving, setSaving] = useState(false);

    const save = async () => {
        setSaving(true);
        try {
            const saved = await putBasket(basket.id, {name, description});
            upsert(saved);
            refresh();
            toast.success(t('basket.updated', 'Basket updated'));
        } catch (e: any) {
            toast.error(e?.message);
        } finally {
            setSaving(false);
        }
    };

    return (
        <div className="space-y-3">
            <FormRow label={t('common.name', 'Name')}>
                <Input value={name} onChange={e => setName(e.target.value)} />
            </FormRow>
            <FormRow label={t('common.description', 'Description')}>
                <Textarea
                    value={description}
                    onChange={e => setDescription(e.target.value)}
                />
            </FormRow>
            <div className="flex justify-end">
                <Button onClick={save} loading={saving} disabled={!name.trim()}>
                    <SaveIcon /> {t('common.save', 'Save')}
                </Button>
            </div>
        </div>
    );
}

function PermissionsTab({basket}: TabProps) {
    const {t} = useTranslation();

    return (
        <AclEditor
            objectType={PermissionObject.Basket}
            objectId={basket.id}
            definitions={genericPermissions(t)}
        />
    );
}

function OperationsTab({basket, onClose}: TabProps) {
    const {t} = useTranslation();
    const {openModal} = useModals();
    const remove = useBasketStore(s => s.remove);

    return (
        <Alert
            variant="destructive"
            title={t('asset.ops.danger_zone', 'Danger zone')}
        >
            <Button
                variant="destructive"
                size="sm"
                className="mt-2"
                onClick={() =>
                    openModal(ConfirmDialog, {
                        title: t(
                            'basket.delete.title',
                            'Delete basket "{{name}}"?',
                            {name: basket.name}
                        ),
                        destructive: true,
                        textToType: basket.name,
                        onConfirm: () => remove(basket.id),
                        onConfirmed: onClose,
                    })
                }
            >
                <Trash2Icon /> {t('common.delete', 'Delete')}
            </Button>
        </Alert>
    );
}
