'use client';

import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import type {TFunction} from 'i18next';
import {SaveIcon} from 'lucide-react';
import {toast} from 'sonner';
import {Button} from '@/components/ui/button';
import {FormRow, Input} from '@/components/ui/input';
import {LabeledControl, Switch} from '@/components/ui/controls';
import {Badge} from '@/components/ui/misc';
import {AclEditor} from '@/features/permissions/AclEditor';
import {
    PermissionDefinition,
    PermissionObject,
} from '@/features/permissions/permissionTypes';
import {useDirtyState} from '@/lib/navigation/unsavedChanges';

/** What attribute and rendition policies have in common */
type Policy = {
    id: string;
    name: string;
    public: boolean;
    editable?: boolean;
};

export type PolicyData = Pick<Policy, 'name' | 'public' | 'editable'>;

/** Public/private, then editable or read only: the latter only when public */
export function PolicyBadges({policy}: {policy: Policy}) {
    const {t} = useTranslation();

    return (
        <>
            <Badge variant={policy.public ? 'success' : 'muted'}>
                {policy.public
                    ? t('common.public', 'Public')
                    : t('common.private', 'Private')}
            </Badge>
            {policy.public ? (
                <Badge variant={policy.editable ? 'secondary' : 'muted'}>
                    {policy.editable
                        ? t('policy.editable', 'Editable')
                        : t('policy.read_only', 'Read only')}
                </Badge>
            ) : null}
        </>
    );
}

/**
 * A private policy cannot be editable by everyone: who may view and edit is
 * granted below. A public one that is not editable grants the edition.
 */
export function PolicyForm<P extends Policy>({
    policy,
    save: persist,
    objectType,
    permissions,
    onSaved,
}: {
    policy?: P;
    save: (data: PolicyData) => Promise<P>;
    objectType: PermissionObject;
    permissions: (t: TFunction, isPublic: boolean) => PermissionDefinition[];
    onSaved: (p: P) => void;
}) {
    const {t} = useTranslation();
    const [name, setName] = useState(policy?.name ?? '');
    const [isPublic, setIsPublic] = useState(policy?.public ?? true);
    const [editable, setEditable] = useState(
        policy ? !!policy.editable && policy.public : true
    );
    const [saving, setSaving] = useState(false);
    const {markSaved} = useDirtyState({name, isPublic, editable});

    const save = async () => {
        setSaving(true);
        try {
            const saved = await persist({name, public: isPublic, editable});
            toast.success(t('policy.saved', 'Policy saved'));
            markSaved();
            onSaved(saved);
        } catch (e: any) {
            toast.error(e?.message);
        } finally {
            setSaving(false);
        }
    };

    return (
        <div className="space-y-4">
            <FormRow label={t('common.name', 'Name')}>
                <Input value={name} onChange={e => setName(e.target.value)} />
            </FormRow>
            <LabeledControl
                label={t('common.public', 'Public')}
                description={t(
                    'policy.public_help',
                    'Visible to everyone who can see the asset. Otherwise, grant access below.'
                )}
            >
                <Switch
                    checked={isPublic}
                    onCheckedChange={v => {
                        setIsPublic(v);
                        if (!v) {
                            setEditable(false);
                        }
                    }}
                />
            </LabeledControl>
            <LabeledControl
                label={t('policy.editable', 'Editable')}
                description={
                    isPublic
                        ? t(
                              'policy.editable_help',
                              'Editable by everyone who can edit the asset. Otherwise, grant the edition below.'
                          )
                        : t(
                              'policy.editable_private_help',
                              'A private policy is not editable by everyone: grant the edition below.'
                          )
                }
            >
                <Switch
                    data-testid="policy-editable"
                    checked={editable}
                    disabled={!isPublic}
                    onCheckedChange={setEditable}
                />
            </LabeledControl>
            <div className="flex justify-end">
                <Button onClick={save} loading={saving} disabled={!name.trim()}>
                    <SaveIcon /> {t('common.save', 'Save')}
                </Button>
            </div>
            {policy && !editable ? (
                <div className="border-t pt-4">
                    <h4 className="mb-2 text-sm font-semibold">
                        {t('collection.manage.permissions', 'Permissions')}
                    </h4>
                    <AclEditor
                        objectType={objectType}
                        objectId={policy.id}
                        definitions={permissions(t, isPublic)}
                    />
                </div>
            ) : null}
        </div>
    );
}
