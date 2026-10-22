'use client';

import {useCallback, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {useQuery} from '@tanstack/react-query';
import {LayoutTemplateIcon, XIcon} from 'lucide-react';
import type {AssetDataTemplate} from '@/types/api';
import {getAssetDataTemplate, getAssetDataTemplates} from '@/lib/api/metadata';
import {FormRow} from '@/components/ui/input';
import {Chip} from '@/components/ui/misc';
import {AsyncCombobox} from '@/components/form/AsyncCombobox';

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
    const pending = value[value.length - 1];
    // Names of the applied templates, for their chips
    const [names, setNames] = useState<Record<string, string>>({});

    useQuery({
        queryKey: ['asset-data-template', pending],
        queryFn: async () => {
            const tpl = await getAssetDataTemplate(pending);
            setNames(n => ({...n, [tpl.id]: tpl.name}));
            onApply(tpl);

            return tpl;
        },
        enabled: !!pending,
        staleTime: Infinity,
    });

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

export {XIcon};
