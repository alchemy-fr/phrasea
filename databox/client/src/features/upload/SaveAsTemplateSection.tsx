'use client';

import {useEffect, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {SaveIcon} from 'lucide-react';
import {toast} from 'sonner';
import type {
    AssetDataTemplate,
    AttributeBatchAction,
    Privacy,
} from '@/types/api';
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
    appliedTemplate,
    onSaved,
}: {
    workspaceId: string;
    collectionId?: string;
    privacy?: Privacy;
    tags: string[];
    attributes: AttributeBatchAction[];
    appliedTemplate?: AssetDataTemplate;
    onSaved?: () => void;
}) {
    const {t} = useTranslation();
    const [name, setName] = useState('');
    const [replace, setReplace] = useState(false);
    const [withCollection, setWithCollection] = useState(!!collectionId);
    // Without it, a template is only offered in its own collection (or at
    // the workspace root for a workspace template)
    const [includeChildren, setIncludeChildren] = useState(true);
    const [withAttributes, setWithAttributes] = useState(true);
    const [withPrivacy, setWithPrivacy] = useState(true);
    const [withTags, setWithTags] = useState(true);
    const [isPublic, setIsPublic] = useState(false);
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        if (!appliedTemplate) {
            setReplace(false);

            return;
        }
        setName(appliedTemplate.name);
        setReplace(true);
        setWithCollection(!!appliedTemplate.collection);
        setIncludeChildren(appliedTemplate.includeCollectionChildren);
        setWithPrivacy(
            appliedTemplate.privacy !== undefined &&
                appliedTemplate.privacy !== null
        );
        setIsPublic(appliedTemplate.public);
    }, [appliedTemplate]);

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
                includeCollectionChildren: includeChildren,
                attributes: withAttributes ? attributes : undefined,
                privacy: withPrivacy ? privacy : undefined,
                tags: withTags
                    ? tags.map(id => iri(EntityName.Tag, id))
                    : undefined,
                public: isPublic,
            } as any;
            if (replace && appliedTemplate) {
                await putAssetDataTemplate(appliedTemplate.id, data);
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
            {appliedTemplate ? (
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
                    label={
                        withCollection && collectionId
                            ? t(
                                  'upload.template_children',
                                  'Including sub-collections'
                              )
                            : t(
                                  'upload.template_all_collections',
                                  'In every collection'
                              )
                    }
                >
                    <Checkbox
                        checked={includeChildren}
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
