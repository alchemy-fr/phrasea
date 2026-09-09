'use client';

import {useTranslation} from 'react-i18next';
import {useQuery} from '@tanstack/react-query';
import {LayoutTemplateIcon, XIcon} from 'lucide-react';
import type {AssetDataTemplate} from '@/types/api';
import {getAssetDataTemplate} from '@/lib/api/metadata';
import {FormRow} from '@/components/ui/input';
import {SimpleSelect} from '@/components/ui/select';
import {Chip} from '@/components/ui/misc';

/**
 * Applies value templates (privacy, tags, attributes) to the upload form.
 */
export function AssetDataTemplateSelect({
    templates,
    value,
    onChange,
    onApply,
}: {
    templates: AssetDataTemplate[];
    value: string[];
    onChange: (ids: string[]) => void;
    onApply: (template: AssetDataTemplate) => void;
}) {
    const {t} = useTranslation();
    const pending = value[value.length - 1];

    useQuery({
        queryKey: ['asset-data-template', pending],
        queryFn: async () => {
            const tpl = await getAssetDataTemplate(pending);
            onApply(tpl);

            return tpl;
        },
        enabled: !!pending,
        staleTime: Infinity,
    });

    return (
        <FormRow label={t('upload.templates', 'Value templates')}>
            <div className="space-y-2">
                <SimpleSelect
                    value={undefined}
                    placeholder={t(
                        'upload.apply_template',
                        'Apply a template…'
                    )}
                    onValueChange={id => {
                        if (!value.includes(id)) {
                            onChange([...value, id]);
                        }
                    }}
                    options={templates.map(tpl => ({
                        value: tpl.id,
                        label: tpl.name,
                    }))}
                />
                {value.length > 0 ? (
                    <div className="flex flex-wrap gap-1">
                        {value.map(id => {
                            const tpl = templates.find(x => x.id === id);

                            return (
                                <Chip
                                    key={id}
                                    size="sm"
                                    icon={
                                        <LayoutTemplateIcon className="size-3" />
                                    }
                                    onRemove={() =>
                                        onChange(value.filter(v => v !== id))
                                    }
                                >
                                    {tpl?.name ?? id}
                                </Chip>
                            );
                        })}
                    </div>
                ) : null}
            </div>
        </FormRow>
    );
}

export {XIcon};
