'use client';

import {useState} from 'react';
import {useQuery} from '@tanstack/react-query';
import {useTranslation} from 'react-i18next';
import {Alert} from '@alchemy/next-ui/ui/misc';
import {FullPageLoader} from '@alchemy/next-ui/ui/loader';
import {getErrorMessage} from '@alchemy/next-ui/utils/errors';
import {Page} from '@/components/layout/AppShell';
import {getTarget, getTargetForm} from '@/lib/api/uploader';
import {routes} from '@/lib/routes';
import {targetsQueryKey} from '@/features/targets/TargetListScreen';
import {AdminTargetList, BackToList} from './AdminTargetList';
import {FormBuilderEditor} from './FormBuilderEditor';
import {targetFormQueryKey} from './queryKeys';
import {RequirePermission} from './RequirePermission';

export function FormListScreen() {
    const {t} = useTranslation();

    return (
        <RequirePermission permission="form_schema">
            <AdminTargetList
                title={t('admin.forms.title', 'Forms')}
                testId="form-target-list"
                has={target => target.hasForm}
                hasLabel={t('admin.forms.has_form', 'Form')}
                hasNotLabel={t('admin.forms.no_form', 'No form')}
                href={target => routes.form(target.id)}
            />
        </RequirePermission>
    );
}

export function FormEditScreen({targetId}: {targetId: string}) {
    return (
        <RequirePermission permission="form_schema">
            <FormEdit targetId={targetId} />
        </RequirePermission>
    );
}

function FormEdit({targetId}: {targetId: string}) {
    const {t} = useTranslation();
    // A new editor once the form is deleted
    const [round, setRound] = useState(0);
    const target = useQuery({
        queryKey: [...targetsQueryKey, targetId],
        queryFn: () => getTarget(targetId),
    });
    const form = useQuery({
        queryKey: targetFormQueryKey(targetId),
        queryFn: () => getTargetForm(targetId),
        staleTime: 0,
    });

    // The editor starts from what is saved, never from a cached copy
    if (target.isPending || form.isPending || !form.isFetchedAfterMount) {
        return <FullPageLoader />;
    }
    if (target.error || form.error) {
        return (
            <Page>
                <Alert variant="destructive">
                    {getErrorMessage(target.error ?? form.error)}
                </Alert>
            </Page>
        );
    }

    return (
        <Page
            title={t('admin.form.edit', 'Form of {{target}}', {
                target: target.data.name,
            })}
            actions={<BackToList href={routes.forms()} />}
            wide
        >
            <FormBuilderEditor
                key={round}
                targetId={targetId}
                targetName={target.data.name}
                form={form.data}
                onDeleted={() => setRound(r => r + 1)}
            />
        </Page>
    );
}
