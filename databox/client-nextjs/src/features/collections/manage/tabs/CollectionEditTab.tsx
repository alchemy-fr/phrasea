'use client';

import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {SaveIcon} from 'lucide-react';
import {toast} from 'sonner';
import type {Privacy} from '@/types/api';
import type {CollectionTabProps} from '../CollectionManageRoute';
import {Button} from '@/components/ui/button';
import {PrivacyField} from '@/components/form/PrivacyField';
import {TranslatableField} from '@/components/form/TranslatableField';
import {putCollection} from '@/lib/api/collections';
import {useCollectionStore} from '../../collectionStore';

export function CollectionEditTab({collection, refresh}: CollectionTabProps) {
    const {t} = useTranslation();
    const upsert = useCollectionStore(s => s.upsertCollection);
    const [name, setName] = useState(collection.name);
    const [translations, setTranslations] = useState<Record<string, string>>(
        collection.translations?.name ?? {}
    );
    const [privacy, setPrivacy] = useState<Privacy | undefined>(
        collection.privacy
    );
    const [saving, setSaving] = useState(false);

    const save = async () => {
        setSaving(true);
        try {
            const updated = await putCollection(collection.id, {
                name,
                privacy,
                translations: {name: translations},
            });
            upsert(updated);
            refresh();
            toast.success(t('collection.edit.saved', 'Collection saved'));
        } catch (e: any) {
            toast.error(e?.message);
        } finally {
            setSaving(false);
        }
    };

    return (
        <div className="max-w-xl space-y-4">
            <TranslatableField
                label={t('common.name', 'Name')}
                value={name}
                onChange={setName}
                translations={translations}
                onTranslationsChange={setTranslations}
                locales={collection.workspace.enabledLocales ?? []}
            />
            <PrivacyField
                value={privacy}
                onChange={setPrivacy}
                inheritedPrivacy={collection.inheritedPrivacy}
            />
            <div className="flex justify-end">
                <Button onClick={save} loading={saving} disabled={!name.trim()}>
                    <SaveIcon /> {t('common.save', 'Save')}
                </Button>
            </div>
        </div>
    );
}
