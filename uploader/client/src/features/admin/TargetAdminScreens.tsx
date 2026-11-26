'use client';

import {ReactNode, useState} from 'react';
import {useRouter} from 'next/navigation';
import {useQuery} from '@tanstack/react-query';
import {useTranslation} from 'react-i18next';
import {PlusIcon} from 'lucide-react';
import {Button} from '@alchemy/ui/ui/button';
import {useModals} from '@alchemy/ui/modals/ModalProvider';
import {
    Alert,
    Tabs,
    TabsContent,
    TabsList,
    TabsTrigger,
} from '@alchemy/ui/ui/misc';
import {FullPageLoader} from '@alchemy/ui/ui/loader';
import {getErrorMessage} from '@alchemy/ui/utils/errors';
import {Page} from '@/components/layout/AppShell';
import {
    getTarget,
    getTargetData,
    getTargetForm,
    getTargetSettings,
} from '@/lib/api/uploader';
import {routes} from '@/lib/routes';
import {targetsQueryKey} from '@/features/targets/TargetListScreen';
import {useUploaderUser} from '@/features/user/useUploaderUser';
import {
    AdminBreadcrumb,
    AdminTargetList,
    type AdminTargetBadge,
} from './AdminTargetList';
import {FormBuilderEditor} from './FormBuilderEditor';
import {
    targetDataQueryKey,
    targetFormQueryKey,
    targetSettingsQueryKey,
} from './queryKeys';
import {RequirePermission} from './RequirePermission';
import {TargetCreateDialog} from './TargetCreateDialog';
import {TargetDataEditor} from './TargetDataEditor';
import {TargetPropertiesEditor} from './TargetPropertiesEditor';

/** Either permission opens the target editor, on the settings it grants */
const TARGET_PERMISSIONS = ['form_schema', 'target_data'] as const;

type Tab = 'properties' | 'form' | 'data';

export function TargetAdminListScreen() {
    return (
        <RequirePermission permission={[...TARGET_PERMISSIONS, 'target']}>
            <TargetAdminList />
        </RequirePermission>
    );
}

function TargetAdminList() {
    const {t} = useTranslation();
    const {data: me} = useUploaderUser();
    const {openModal} = useModals();
    const router = useRouter();

    const create = async () => {
        const created = await openModal(TargetCreateDialog, {});
        if (created) {
            router.push(routes.target(created.id));
        }
    };

    const badges: AdminTargetBadge[] = [];
    if (me?.permissions.form_schema) {
        badges.push({
            key: 'form',
            has: target => target.hasForm,
            label: t('admin.forms.has_form', 'Form'),
            notLabel: t('admin.forms.no_form', 'No form'),
        });
    }
    if (me?.permissions.target_data) {
        badges.push({
            key: 'data',
            has: target => target.hasData,
            label: t('admin.target_data.has_data', 'Data'),
            notLabel: t('admin.target_data.no_data', 'No data'),
        });
    }

    return (
        <AdminTargetList
            title={t('admin.targets.title', 'Targets')}
            testId="admin-target-list"
            badges={badges}
            href={target => routes.target(target.id)}
            actions={
                me?.permissions.target ? (
                    <Button data-testid="target-create" onClick={create}>
                        <PlusIcon /> {t('admin.targets.create', 'New target')}
                    </Button>
                ) : null
            }
        />
    );
}

export function TargetAdminEditScreen({targetId}: {targetId: string}) {
    return (
        <RequirePermission permission={[...TARGET_PERMISSIONS, 'target']}>
            <TargetAdminEdit targetId={targetId} />
        </RequirePermission>
    );
}

function TargetAdminEdit({targetId}: {targetId: string}) {
    const {t} = useTranslation();
    const {data: me} = useUploaderUser();
    const canProps = !!me?.permissions.target;
    const canForm = !!me?.permissions.form_schema;
    const canData = !!me?.permissions.target_data;
    // The first editor the user may open
    const [tab, setTab] = useState<Tab>(
        canProps ? 'properties' : canForm ? 'form' : 'data'
    );
    // A new form editor once the form is deleted
    const [round, setRound] = useState(0);
    const target = useQuery({
        queryKey: [...targetsQueryKey, targetId],
        queryFn: () => getTarget(targetId),
    });
    const settings = useQuery({
        queryKey: targetSettingsQueryKey(targetId),
        queryFn: () => getTargetSettings(targetId),
        staleTime: 0,
        enabled: canProps,
    });
    const form = useQuery({
        queryKey: targetFormQueryKey(targetId),
        queryFn: () => getTargetForm(targetId),
        staleTime: 0,
        enabled: canForm,
    });
    const data = useQuery({
        queryKey: targetDataQueryKey(targetId),
        queryFn: () => getTargetData(targetId),
        staleTime: 0,
        enabled: canData,
    });

    // The editors start from what is saved, never from a cached copy
    if (
        target.isPending ||
        (canProps && (settings.isPending || !settings.isFetchedAfterMount)) ||
        (canForm && (form.isPending || !form.isFetchedAfterMount)) ||
        (canData && (data.isPending || !data.isFetchedAfterMount))
    ) {
        return <FullPageLoader />;
    }
    if (target.error || settings.error || form.error || data.error) {
        return (
            <Page>
                <Alert variant="destructive">
                    {getErrorMessage(
                        target.error ??
                            settings.error ??
                            form.error ??
                            data.error
                    )}
                </Alert>
            </Page>
        );
    }

    const editors: {value: Tab; label: string; content: ReactNode}[] = [];
    if (canProps && settings.data) {
        editors.push({
            value: 'properties',
            label: t('admin.target.tab_properties', 'Properties'),
            content: (
                <TargetPropertiesEditor
                    targetId={targetId}
                    settings={settings.data}
                />
            ),
        });
    }
    if (canForm) {
        editors.push({
            value: 'form',
            label: t('admin.target.tab_form', 'Form'),
            content: (
                <FormBuilderEditor
                    key={round}
                    targetId={targetId}
                    targetName={target.data.name}
                    form={form.data ?? null}
                    onDeleted={() => setRound(r => r + 1)}
                />
            ),
        });
    }
    if (canData) {
        editors.push({
            value: 'data',
            label: t('admin.target.tab_data', 'Data'),
            content: (
                <TargetDataEditor
                    targetId={targetId}
                    data={data.data ?? null}
                />
            ),
        });
    }

    return (
        <Page
            title={t('admin.target.edit', 'Edit {{target}}', {
                target: target.data.name,
            })}
            breadcrumb={
                <AdminBreadcrumb
                    href={routes.targets()}
                    label={t('nav.targets', 'Targets')}
                    current={target.data.name}
                />
            }
            wide
        >
            {editors.length === 1 ? (
                editors[0].content
            ) : (
                <Tabs value={tab} onValueChange={v => setTab(v as Tab)}>
                    <TabsList className="mb-4">
                        {editors.map(editor => (
                            <TabsTrigger
                                key={editor.value}
                                value={editor.value}
                                data-testid={`target-tab-${editor.value}`}
                            >
                                {editor.label}
                            </TabsTrigger>
                        ))}
                    </TabsList>
                    {/* All stay mounted: switching tabs keeps unsaved changes */}
                    {editors.map(editor => (
                        <TabsContent
                            key={editor.value}
                            value={editor.value}
                            forceMount
                            className="data-[state=inactive]:hidden"
                        >
                            {editor.content}
                        </TabsContent>
                    ))}
                </Tabs>
            )}
        </Page>
    );
}
