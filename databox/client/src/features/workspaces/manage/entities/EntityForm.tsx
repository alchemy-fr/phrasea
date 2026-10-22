'use client';

import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {SaveIcon} from 'lucide-react';
import {toast} from 'sonner';
import type {AttributeEntity, EntityList} from '@/types/api';
import {AttributeEntityStatus} from '@/types/api';
import {
    deleteAttributeEntityImage,
    postAttributeEntity,
    putAttributeEntity,
    uploadAttributeEntityImage,
} from '@/lib/api/metadata';
import {Button} from '@/components/ui/button';
import {FormRow, Input} from '@/components/ui/input';
import {SimpleSelect} from '@/components/ui/select';
import {Flag} from '@/components/ui/flag';
import {ColorInput} from '@/components/ui/color-input';
import {FilePicker} from '@/components/form/FilePicker';
import {useDirtyState} from '@/lib/navigation/unsavedChanges';
import {
    checkUploadFile,
    describeUploadRejection,
    ENTITY_IMAGE_LIMITS,
} from '@/lib/uploadLimits';

export function EntityForm({
    entity,
    list,
    locales,
    onSaved,
    onCancel,
}: {
    entity?: AttributeEntity;
    list: EntityList;
    locales: string[];
    onSaved: (entity: AttributeEntity) => void;
    onCancel: () => void;
}) {
    const {t, i18n} = useTranslation();
    const [value, setValue] = useState(entity?.value ?? '');
    const [note, setNote] = useState(entity?.note ?? '');
    const [status, setStatus] = useState(
        String(entity?.status ?? AttributeEntityStatus.Approved)
    );
    const [emoji, setEmoji] = useState(entity?.emoji ?? '');
    const [color, setColor] = useState(entity?.color ?? '');
    // A photo to upload, '' to remove the current one, undefined: unchanged
    const [image, setImage] = useState<File | ''>();
    const [translations, setTranslations] = useState<Record<string, string>>(
        entity?.translations ?? {}
    );
    const [synonyms, setSynonyms] = useState<Record<string, string>>(
        Object.fromEntries(
            Object.entries(entity?.synonyms ?? {}).map(([k, v]) => [
                k,
                v.join(', '),
            ])
        )
    );
    const [saving, setSaving] = useState(false);
    const {markSaved} = useDirtyState({
        value,
        note,
        status,
        emoji,
        color,
        translations,
        synonyms,
        image: image === '' ? 'removed' : image?.name,
    });

    const save = async () => {
        setSaving(true);
        try {
            const data: Partial<AttributeEntity> = {
                value,
                note: note.trim() || null,
                status: Number(status) as AttributeEntityStatus,
                emoji: list.withEmojis ? emoji || undefined : undefined,
                color: list.withColors ? color || undefined : undefined,
                translations: list.withTranslations ? translations : undefined,
                synonyms: list.withSynonyms
                    ? Object.fromEntries(
                          Object.entries(synonyms).map(([k, v]) => [
                              k,
                              v
                                  .split(',')
                                  .map(s => s.trim())
                                  .filter(Boolean),
                          ])
                      )
                    : undefined,
            };
            let saved = entity
                ? await putAttributeEntity(entity.id, data)
                : await postAttributeEntity(list.id, data);
            // The value is saved even when its photo is not: the photo
            // can be sent again from the edit dialog
            try {
                if (list.withImages && image) {
                    const rejection = checkUploadFile(
                        image,
                        ENTITY_IMAGE_LIMITS
                    );
                    if (rejection) {
                        throw new Error(
                            describeUploadRejection(
                                t,
                                image,
                                rejection,
                                ENTITY_IMAGE_LIMITS,
                                i18n.language
                            )
                        );
                    }
                    saved = await uploadAttributeEntityImage(saved.id, image);
                } else if (list.withImages && image === '') {
                    await deleteAttributeEntityImage(saved.id);
                    saved = {...saved, image: null, thumbnail: null};
                }
            } catch (e: any) {
                toast.error(
                    t(
                        'entity.image_error',
                        'The photo was not saved: {{message}}',
                        {
                            message: e?.message,
                        }
                    )
                );
            }
            setImage(undefined);
            toast.success(t('entity.saved', 'Value saved'));
            markSaved();
            onSaved(saved);
        } catch (e: any) {
            toast.error(e?.message);
        } finally {
            setSaving(false);
        }
    };

    return (
        <div className="space-y-3">
            <FormRow label={t('entity.value', 'Value')}>
                <Input
                    value={value}
                    onChange={e => setValue(e.target.value)}
                    autoFocus
                />
            </FormRow>
            <FormRow label={t('entity.note', 'Note')}>
                <Input
                    value={note}
                    onChange={e => setNote(e.target.value)}
                    placeholder={t(
                        'entity.note_placeholder',
                        'Tells apart two values with the same name'
                    )}
                />
            </FormRow>
            <FormRow label={t('entity.status', 'Status')}>
                <SimpleSelect
                    value={status}
                    onValueChange={setStatus}
                    options={[
                        {
                            value: String(AttributeEntityStatus.Pending),
                            label: t('entity.pending', 'Pending'),
                        },
                        {
                            value: String(AttributeEntityStatus.Approved),
                            label: t('entity.approved', 'Approved'),
                        },
                        {
                            value: String(AttributeEntityStatus.Rejected),
                            label: t('entity.rejected', 'Rejected'),
                        },
                    ]}
                />
            </FormRow>
            {list.withEmojis ? (
                <FormRow label={t('entity.emoji', 'Emoji')}>
                    <Input
                        value={emoji}
                        onChange={e => setEmoji(e.target.value)}
                        className="w-24"
                        placeholder="🎨"
                    />
                </FormRow>
            ) : null}
            {list.withColors ? (
                <FormRow label={t('tag.color', 'Color')}>
                    <ColorInput value={color} onChange={setColor} />
                </FormRow>
            ) : null}
            {list.withImages ? (
                <FormRow label={t('entity.image', 'Photo')}>
                    <FilePicker
                        testId="entity-image"
                        {...ENTITY_IMAGE_LIMITS}
                        value={image}
                        onChange={setImage}
                        current={
                            entity?.thumbnail ? (
                                // eslint-disable-next-line @next/next/no-img-element
                                <img
                                    src={entity.thumbnail}
                                    alt=""
                                    className="size-16 rounded-full object-cover"
                                />
                            ) : null
                        }
                        uploadLabel={t('entity.image_upload', 'Upload a photo')}
                        removeLabel={t(
                            'entity.image_remove',
                            'Remove the photo'
                        )}
                        removedLabel={t(
                            'entity.image_removed',
                            'The photo will be removed'
                        )}
                    />
                </FormRow>
            ) : null}
            {list.withTranslations && locales.length > 0 ? (
                <FormRow label={t('entity.translations', 'Translations')}>
                    <div className="space-y-1">
                        {locales.map(l => (
                            <div key={l} className="flex items-center gap-2">
                                <span className="w-12 text-xs">
                                    <Flag locale={l} /> {l}
                                </span>
                                <Input
                                    value={translations[l] ?? ''}
                                    onChange={e =>
                                        setTranslations({
                                            ...translations,
                                            [l]: e.target.value,
                                        })
                                    }
                                    className="h-8"
                                />
                            </div>
                        ))}
                    </div>
                </FormRow>
            ) : null}
            {list.withSynonyms && locales.length > 0 ? (
                <FormRow
                    label={t('entity.synonyms', 'Synonyms (comma separated)')}
                >
                    <div className="space-y-1">
                        {locales.map(l => (
                            <div key={l} className="flex items-center gap-2">
                                <span className="w-12 text-xs">
                                    <Flag locale={l} /> {l}
                                </span>
                                <Input
                                    value={synonyms[l] ?? ''}
                                    onChange={e =>
                                        setSynonyms({
                                            ...synonyms,
                                            [l]: e.target.value,
                                        })
                                    }
                                    className="h-8"
                                />
                            </div>
                        ))}
                    </div>
                </FormRow>
            ) : null}
            <div className="flex justify-end gap-2">
                <Button variant="ghost" size="sm" onClick={onCancel}>
                    {t('common.cancel', 'Cancel')}
                </Button>
                <Button
                    size="sm"
                    onClick={save}
                    loading={saving}
                    disabled={!value.trim()}
                >
                    <SaveIcon /> {t('common.save', 'Save')}
                </Button>
            </div>
        </div>
    );
}
