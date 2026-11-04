'use client';

import {useCallback, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {LayoutTemplateIcon} from 'lucide-react';
import {toast} from 'sonner';
import type {AssetDataTemplate} from '@/types/api';
import {getAssetDataTemplate, getAssetDataTemplates} from '@/lib/api/metadata';
import {FormRow} from '@alchemy/next-ui/ui/input';
import {Chip} from '@alchemy/next-ui/ui/misc';
import {AsyncCombobox} from '@alchemy/next-ui/form/AsyncCombobox';

/**
 * Applies value templates (privacy, tags, attributes) to the upload form.
 */
export function AssetDataTemplateSelect({
    workspaceId,
    collectionId,
    value,
    onChange,
    onApply,
}: {
    workspaceId: string;
    collectionId?: string;
    value: string[];
    onChange: (ids: string[]) => void;
    onApply: (template: AssetDataTemplate) => void;
}) {
    const {t} = useTranslation();
    // Names of the applied templates, for their chips
    const [names, setNames] = useState<Record<string, string>>({});

    // Fetched at each selection: a template picked again must be applied again
    const apply = async (id: string) => {
        try {
            onApply(await getAssetDataTemplate(id));
        } catch (e: any) {
            onChange(value.filter(v => v !== id));
            toast.error(e?.message);
        }
    };

    const load = useCallback(
        async (query: string, signal: AbortSignal, cursor?: string) => {
            const page = await getAssetDataTemplates(
                {
                    workspace: workspaceId,
                    collection: collectionId,
                    query: query || undefined,
                    url: cursor,
                },
                signal
            );

            return {
                options: page.items.map(tpl => ({
                    value: tpl.id,
                    label: tpl.name,
                    item: tpl,
                })),
                next: page.next,
            };
        },
        [workspaceId, collectionId]
    );

    return (
        <FormRow label={t('upload.templates', 'Value templates')}>
            <div className="space-y-2">
                <AsyncCombobox<AssetDataTemplate>
                    queryKey={[
                        'asset-data-templates',
                        workspaceId,
                        collectionId,
                    ]}
                    loadPage={load}
                    value={undefined}
                    onChange={(id, opt) => {
                        if (id && !value.includes(id)) {
                            if (opt) {
                                setNames(n => ({...n, [id]: opt.label}));
                            }
                            onChange([...value, id]);
                            apply(id);
                        }
                    }}
                    placeholder={t(
                        'upload.apply_template',
                        'Apply a template…'
                    )}
                />
                {value.length > 0 ? (
                    <div className="flex flex-wrap gap-1">
                        {value.map(id => (
                            <Chip
                                key={id}
                                size="sm"
                                icon={<LayoutTemplateIcon className="size-3" />}
                                onRemove={() =>
                                    onChange(value.filter(v => v !== id))
                                }
                            >
                                {names[id] ?? id}
                            </Chip>
                        ))}
                    </div>
                ) : null}
            </div>
        </FormRow>
    );
}
