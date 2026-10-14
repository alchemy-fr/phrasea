'use client';

import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {SaveIcon} from 'lucide-react';
import {toast} from 'sonner';
import type {AttributeBatchAction, Privacy} from '@/types/api';
import {EntityName} from '@/types/api';
import {Button} from '@/components/ui/button';
import {FormRow, Input} from '@/components/ui/input';
import {Checkbox, LabeledControl} from '@/components/ui/controls';
import {postAssetDataTemplate, putAssetDataTemplate} from '@/lib/api/metadata';
import {iri} from '@/lib/utils/iri';

export function SaveAsTemplateSection({
    workspaceId,
    collectionId,
    privacy,
    tags,
    attributes,
    appliedTemplateId,
    onSaved,
}: {
    workspaceId: string;
    collectionId?: string;
    privacy?: Privacy;
    tags: string[];
    attributes: AttributeBatchAction[];
    appliedTemplateId?: string;
    onSaved?: () => void;
}) {
    const {t} = useTranslation();
    const [name, setName] = useState('');
    const [replace, setReplace] = useState(false);
    const [withCollection, setWithCollection] = useState(!!collectionId);
    const [includeChildren, setIncludeChildren] = useState(false);
    const [withAttributes, setWithAttributes] = useState(true);
    const [withPrivacy, setWithPrivacy] = useState(true);
    const [withTags, setWithTags] = useState(true);
    const [isPublic, setIsPublic] = useState(false);
    const [loading, setLoading] = useState(false);

    const save = async () => {
        setLoading(true);
        try {
            const data = {
                name,
                workspace: iri(EntityName.Workspace, workspaceId),
                collection:
                    withCollection && collectionId
                        ? iri(EntityName.Collection, collectionId)
                        : undefined,
                includeCollectionChildren: withCollection && includeChildren,
                attributes: withAttributes ? attributes : undefined,
                privacy: withPrivacy ? privacy : undefined,
                tags: withTags
                    ? tags.map(id => iri(EntityName.Tag, id))
                    : undefined,
                public: isPublic,
            } as any;
            if (replace && appliedTemplateId) {
                await putAssetDataTemplate(appliedTemplateId, data);
            } else {
                await postAssetDataTemplate(data);
            }
            toast.success(t('upload.template_saved', 'Template saved'));
            setName('');
            onSaved?.();
        } catch (e: any) {
            toast.error(e?.message);
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="space-y-3">
            <FormRow label={t('upload.template_name', 'Template name')}>
                <Input value={name} onChange={e => setName(e.target.value)} />
            </FormRow>
            {appliedTemplateId ? (
                <LabeledControl
                    label={t(
                        'upload.template_replace',
                        'Replace the applied template'
                    )}
                >
                    <Checkbox
                        checked={replace}
                        onCheckedChange={v => setReplace(v === true)}
                    />
                </LabeledControl>
            ) : null}
            <div className="grid grid-cols-2 gap-2">
                <LabeledControl
                    label={t(
                        'upload.template_collection',
                        'Remember collection'
                    )}
                >
                    <Checkbox
                        checked={withCollection}
                        disabled={!collectionId}
                        onCheckedChange={v => setWithCollection(v === true)}
                    />
                </LabeledControl>
                <LabeledControl
                    label={t(
                        'upload.template_children',
                        'Including sub-collections'
                    )}
                >
                    <Checkbox
                        checked={includeChildren}
                        disabled={!withCollection}
                        onCheckedChange={v => setIncludeChildren(v === true)}
                    />
                </LabeledControl>
                <LabeledControl label={t('upload.attributes', 'Attributes')}>
                    <Checkbox
                        checked={withAttributes}
                        onCheckedChange={v => setWithAttributes(v === true)}
                    />
                </LabeledControl>
                <LabeledControl label={t('common.privacy', 'Privacy')}>
                    <Checkbox
                        checked={withPrivacy}
                        onCheckedChange={v => setWithPrivacy(v === true)}
                    />
                </LabeledControl>
                <LabeledControl label={t('common.tags', 'Tags')}>
                    <Checkbox
                        checked={withTags}
                        onCheckedChange={v => setWithTags(v === true)}
                    />
                </LabeledControl>
                <LabeledControl label={t('common.public', 'Public')}>
                    <Checkbox
                        checked={isPublic}
                        onCheckedChange={v => setIsPublic(v === true)}
                    />
                </LabeledControl>
            </div>
            <Button
                variant="outline"
                size="sm"
                onClick={save}
                disabled={!name.trim()}
                loading={loading}
            >
                <SaveIcon /> {t('upload.save_template', 'Save template')}
            </Button>
        </div>
    );
}
