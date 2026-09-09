'use client';

import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {useQuery} from '@tanstack/react-query';
import {SaveIcon, XIcon, GripVerticalIcon} from 'lucide-react';
import {toast} from 'sonner';
import {AssetStatus} from '@/types/api';
import type {WorkspaceTabProps} from '../WorkspaceManageRoute';
import {putWorkspace} from '@/lib/api/collections';
import {getLocales} from '@/lib/api/metadata';
import {Button} from '@/components/ui/button';
import {FormRow, Input} from '@/components/ui/input';
import {LabeledControl, Switch} from '@/components/ui/controls';
import {SimpleSelect} from '@/components/ui/select';
import {TranslatableField} from '@/components/form/TranslatableField';
import {AsyncCombobox} from '@/components/form/AsyncCombobox';
import {assetStatusLabels} from '@/features/attributes/types/registry';
import {useCollectionStore} from '@/features/collections/collectionStore';
import {Flag} from '@/components/ui/flag';

export function WorkspaceEditTab({workspace, refresh}: WorkspaceTabProps) {
    const {t} = useTranslation();
    const upsert = useCollectionStore(s => s.upsertWorkspace);
    const [name, setName] = useState(workspace.name);
    const [translations, setTranslations] = useState<Record<string, string>>(
        workspace.translations?.name ?? {}
    );
    const [isPublic, setIsPublic] = useState(workspace.public);
    const [locales, setLocales] = useState<string[]>(
        workspace.enabledLocales ?? []
    );
    const [fallbacks, setFallbacks] = useState<string[]>(
        workspace.localeFallbacks ?? []
    );
    const [retention, setRetention] = useState(
        String(workspace.trashRetentionDelay ?? '')
    );
    const [defaultStatus, setDefaultStatus] = useState(
        String(workspace.assetDefaultStatus ?? AssetStatus.Accepted)
    );
    const [analysisRequired, setAnalysisRequired] = useState(
        !!workspace.fileAnalysisRequired
    );
    const [saving, setSaving] = useState(false);
    const allLocales = useQuery({
        queryKey: ['locales'],
        queryFn: getLocales,
        staleTime: Infinity,
    });

    const save = async () => {
        setSaving(true);
        try {
            const updated = await putWorkspace(workspace.id, {
                name,
                translations: {name: translations},
                public: isPublic,
                enabledLocales: locales,
                localeFallbacks: fallbacks,
                trashRetentionDelay: retention ? Number(retention) : undefined,
                assetDefaultStatus: Number(defaultStatus) as AssetStatus,
                fileAnalysisRequired: analysisRequired,
            } as any);
            upsert(updated);
            refresh();
            toast.success(t('workspace.saved', 'Workspace saved'));
        } catch (e: any) {
            toast.error(e?.message);
        } finally {
            setSaving(false);
        }
    };

    const localeOptions = (allLocales.data ?? []).map(l => ({
        value: l.id,
        label: `${l.name} (${l.id})`,
    }));

    return (
        <div className="max-w-2xl space-y-4">
            <TranslatableField
                label={t('workspace.title', 'Title')}
                value={name}
                onChange={setName}
                translations={translations}
                onTranslationsChange={setTranslations}
                locales={locales}
            />
            <LabeledControl
                label={t('common.public', 'Public')}
                description={t(
                    'workspace.public_help',
                    'Public workspaces are visible to every user.'
                )}
            >
                <Switch checked={isPublic} onCheckedChange={setIsPublic} />
            </LabeledControl>
            <FormRow
                label={t(
                    'workspace.enabled_locales',
                    'Enabled locales (ordered)'
                )}
            >
                <LocaleList
                    value={locales}
                    onChange={setLocales}
                    options={localeOptions}
                />
            </FormRow>
            <FormRow
                label={t('workspace.fallback_locales', 'Fallback locales')}
            >
                <LocaleList
                    value={fallbacks}
                    onChange={setFallbacks}
                    options={localeOptions}
                />
            </FormRow>
            <div className="grid gap-4 sm:grid-cols-2">
                <FormRow
                    label={t(
                        'workspace.trash_retention',
                        'Trash retention (days)'
                    )}
                >
                    <Input
                        type="number"
                        min={0}
                        value={retention}
                        onChange={e => setRetention(e.target.value)}
                    />
                </FormRow>
                <FormRow
                    label={t(
                        'workspace.default_status',
                        'Default asset status'
                    )}
                >
                    <SimpleSelect
                        value={defaultStatus}
                        onValueChange={setDefaultStatus}
                        options={Object.entries(assetStatusLabels(t)).map(
                            ([k, label]) => ({value: k, label})
                        )}
                    />
                </FormRow>
            </div>
            <LabeledControl
                label={t(
                    'workspace.analysis_required',
                    'Requires file analysis'
                )}
                description={t(
                    'workspace.analysis_required_help',
                    'New files are quarantined until the analyzers accept them.'
                )}
            >
                <Switch
                    checked={analysisRequired}
                    onCheckedChange={setAnalysisRequired}
                />
            </LabeledControl>
            <div className="flex justify-end">
                <Button onClick={save} loading={saving} disabled={!name.trim()}>
                    <SaveIcon /> {t('common.save', 'Save')}
                </Button>
            </div>
        </div>
    );
}

function LocaleList({
    value,
    onChange,
    options,
}: {
    value: string[];
    onChange: (v: string[]) => void;
    options: {value: string; label: string}[];
}) {
    const {t} = useTranslation();

    return (
        <div className="space-y-2">
            <ul className="space-y-1">
                {value.map((l, i) => (
                    <li
                        key={l}
                        className="flex items-center gap-2 rounded-md border px-2 py-1 text-sm"
                    >
                        <GripVerticalIcon className="size-4 text-muted-foreground" />
                        <Flag locale={l} />
                        <span className="flex-1">
                            {options.find(o => o.value === l)?.label ?? l}
                        </span>
                        <Button
                            variant="ghost"
                            size="icon-xs"
                            disabled={i === 0}
                            onClick={() => onChange(move(value, i, i - 1))}
                            aria-label="Up"
                        >
                            ↑
                        </Button>
                        <Button
                            variant="ghost"
                            size="icon-xs"
                            disabled={i === value.length - 1}
                            onClick={() => onChange(move(value, i, i + 1))}
                            aria-label="Down"
                        >
                            ↓
                        </Button>
                        <Button
                            variant="ghost"
                            size="icon-xs"
                            onClick={() => onChange(value.filter(x => x !== l))}
                            aria-label={t('common.remove', 'Remove')}
                        >
                            <XIcon />
                        </Button>
                    </li>
                ))}
            </ul>
            <AsyncCombobox
                queryKey={['locale-options']}
                loadOptions={async q =>
                    options
                        .filter(
                            o =>
                                !value.includes(o.value) &&
                                o.label.toLowerCase().includes(q.toLowerCase())
                        )
                        .slice(0, 50)
                }
                value={undefined}
                onChange={v => v && onChange([...value, v])}
                placeholder={t('workspace.add_locale', 'Add a locale…')}
            />
        </div>
    );
}

function move<T>(list: T[], from: number, to: number): T[] {
    const next = [...list];
    const [item] = next.splice(from, 1);
    next.splice(to, 0, item);

    return next;
}
