'use client';

import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {useQuery} from '@tanstack/react-query';
import {
    ArrowLeftIcon,
    DownloadIcon,
    MergeIcon,
    PlusIcon,
    SaveIcon,
    ThumbsDownIcon,
    ThumbsUpIcon,
    Trash2Icon,
    UploadIcon,
    EraserIcon,
    MoreVerticalIcon,
} from 'lucide-react';
import {toast} from 'sonner';
import type {AttributeEntity, EntityList} from '@/types/api';
import {AttributeEntityStatus} from '@/types/api';
import type {WorkspaceTabProps} from '../WorkspaceManageRoute';
import {DefinitionManager} from '../DefinitionManager';
import {
    clearEntityList,
    deleteAttributeEntity,
    deleteEntityList,
    exportEntityList,
    getAttributeEntities,
    getEntityLists,
    importEntityList,
    mergeAttributeEntities,
    postAttributeEntity,
    postEntityList,
    putAttributeEntity,
    putEntityList,
} from '@/lib/api/metadata';
import {Button} from '@/components/ui/button';
import {FormRow, Input, Textarea} from '@/components/ui/input';
import {Checkbox, LabeledControl} from '@/components/ui/controls';
import {Badge, Skeleton} from '@/components/ui/misc';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/menu';
import {SimpleSelect} from '@/components/ui/select';
import {EntityChip} from '@/components/chips';
import {useModals, type ModalProps} from '@/components/modals/ModalProvider';
import {ConfirmDialog} from '@/components/ui/confirm';
import {FormDialog} from '@/components/modals/FormDialog';
import {downloadUrl} from '@/lib/utils/misc';
import {useDebouncedValue} from '@/hooks/useDebouncedValue';
import {Flag} from '@/components/ui/flag';
import {cn} from '@/lib/utils/cn';
import {ColorInput} from '@/components/ui/color-input';
import {
    confirmLeave,
    UnsavedChangesScope,
    useDirtyState,
    useUnsavedChangesChildScope,
} from '@/lib/navigation/unsavedChanges';

export function EntityListsTab({workspace}: WorkspaceTabProps) {
    const {t} = useTranslation();
    const lists = useQuery({
        queryKey: ['entity-lists', workspace.id],
        queryFn: () => getEntityLists({workspaceId: workspace.id}),
    });

    return (
        <DefinitionManager<EntityList>
            items={lists.data?.items}
            loading={lists.isLoading}
            onChanged={() => lists.refetch()}
            filter={(l, q) => l.name.toLowerCase().includes(q)}
            renderItem={l => (
                <span className="flex items-center gap-2">
                    <span className="flex-1 truncate">{l.name}</span>
                    {l.allowNewValues ? (
                        <Badge variant="muted">
                            {t('entity_list.open', 'open')}
                        </Badge>
                    ) : null}
                </span>
            )}
            onDelete={l => deleteEntityList(l.id)}
            createLabel={t('entity_list.create', 'New list')}
            manageLabel={t('entity_list.manage', 'Values')}
            renderManage={(l, back) => (
                <EntityManager
                    list={l}
                    workspaceLocales={workspace.enabledLocales ?? []}
                    onBack={back}
                />
            )}
            renderForm={(l, onSaved) => (
                <ListForm
                    key={l?.id ?? 'new'}
                    list={l}
                    workspaceId={workspace.id}
                    onSaved={onSaved}
                />
            )}
        />
    );
}

function ListForm({
    list,
    workspaceId,
    onSaved,
}: {
    list?: EntityList;
    workspaceId: string;
    onSaved: (l: EntityList) => void;
}) {
    const {t} = useTranslation();
    const [form, setForm] = useState({
        name: list?.name ?? '',
        allowNewValues: list?.allowNewValues ?? false,
        approveNewValues: list?.approveNewValues ?? false,
        withTranslations: list?.withTranslations ?? false,
        withSynonyms: list?.withSynonyms ?? false,
        withEmojis: list?.withEmojis ?? false,
        withColors: list?.withColors ?? false,
    });
    const [saving, setSaving] = useState(false);
    const {markSaved} = useDirtyState(form);
    const set = <K extends keyof typeof form>(k: K, v: (typeof form)[K]) =>
        setForm(f => ({...f, [k]: v}));

    const save = async () => {
        setSaving(true);
        try {
            const saved = list
                ? await putEntityList(list.id, form)
                : await postEntityList(workspaceId, form);
            toast.success(t('entity_list.saved', 'List saved'));
            markSaved();
            onSaved(saved);
        } catch (e: any) {
            toast.error(e?.message);
        } finally {
            setSaving(false);
        }
    };

    const flags: [keyof typeof form, string][] = [
        [
            'allowNewValues',
            t(
                'entity_list.allow_new',
                'Accept new values from attribute fields'
            ),
        ],
        [
            'approveNewValues',
            t(
                'entity_list.approve_new',
                'New values are approved automatically'
            ),
        ],
        [
            'withTranslations',
            t('entity_list.with_translations', 'Translations'),
        ],
        ['withSynonyms', t('entity_list.with_synonyms', 'Synonyms')],
        ['withEmojis', t('entity_list.with_emojis', 'Emojis')],
        ['withColors', t('entity_list.with_colors', 'Colors')],
    ];

    return (
        <div className="space-y-4">
            <FormRow label={t('common.name', 'Name')}>
                <Input
                    value={form.name}
                    onChange={e => set('name', e.target.value)}
                />
            </FormRow>
            <div className="grid gap-2 sm:grid-cols-2">
                {flags.map(([k, label]) => (
                    <LabeledControl key={k} label={label}>
                        <Checkbox
                            checked={!!form[k]}
                            onCheckedChange={v => set(k, (v === true) as never)}
                        />
                    </LabeledControl>
                ))}
            </div>
            <div className="flex justify-end">
                <Button
                    onClick={save}
                    loading={saving}
                    disabled={!form.name.trim()}
                >
                    <SaveIcon /> {t('common.save', 'Save')}
                </Button>
            </div>
        </div>
    );
}

/**
 * Values of an entity list: search, CRUD, moderation (approve / reject),
 * bulk delete, merge, export / import and clear.
 */
function EntityManager({
    list,
    workspaceLocales,
    onBack,
}: {
    list: EntityList;
    workspaceLocales: string[];
    onBack: () => void;
}) {
    const {t} = useTranslation();
    const {openModal} = useModals();
    const [query, setQuery] = useState('');
    const debounced = useDebouncedValue(query, 250);
    const [selected, setSelected] = useState<string[]>([]);
    const [editing, setEditing] = useState<AttributeEntity | 'new' | null>(
        null
    );
    // The value form: leaving the value being edited asks first when it is dirty
    const formScope = useUnsavedChangesChildScope();
    const leaveForm = (next: () => void) => {
        void confirmLeave(formScope).then(leave => leave && next());
    };
    const edit = (next: AttributeEntity | 'new' | null) => {
        const keyOf = (e: typeof next) =>
            e === null || e === 'new' ? e : e.id;
        if (keyOf(next) !== keyOf(editing)) {
            leaveForm(() => setEditing(next));
        }
    };
    const entities = useQuery({
        queryKey: ['attribute-entities', 'manage', list.id, debounced],
        queryFn: () =>
            getAttributeEntities({
                list: list.id,
                query: debounced || undefined,
            }),
    });
    const items = entities.data?.items ?? [];
    const refresh = () => entities.refetch();

    const setStatus = async (
        e: AttributeEntity,
        status: AttributeEntityStatus
    ) => {
        await putAttributeEntity(e.id, {status});
        void refresh();
    };

    return (
        <div className="space-y-3">
            <div className="flex flex-wrap items-center gap-2">
                <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => leaveForm(onBack)}
                >
                    <ArrowLeftIcon /> {t('common.back', 'Back')}
                </Button>
                <h3 className="text-sm font-semibold">{list.name}</h3>
                <Input
                    value={query}
                    onChange={e => setQuery(e.target.value)}
                    placeholder={t('common.search', 'Search…')}
                    className="h-8 w-56"
                />
                <Button size="sm" variant="outline" onClick={() => edit('new')}>
                    <PlusIcon /> {t('entity.create', 'New value')}
                </Button>
                {selected.length > 0 ? (
                    <>
                        <Button
                            size="sm"
                            variant="outline"
                            disabled={selected.length < 2}
                            onClick={() =>
                                openModal(MergeEntitiesDialog, {
                                    entities: items.filter(e =>
                                        selected.includes(e.id)
                                    ),
                                    onMerged: () => {
                                        setSelected([]);
                                        void refresh();
                                    },
                                })
                            }
                        >
                            <MergeIcon /> {t('entity.merge', 'Merge')}
                        </Button>
                        <Button
                            size="sm"
                            variant="ghost"
                            className="text-destructive"
                            onClick={() =>
                                openModal(ConfirmDialog, {
                                    title: t(
                                        'entity.delete_selected',
                                        'Delete {{count}} value(s)?',
                                        {count: selected.length}
                                    ),
                                    destructive: true,
                                    onConfirm: async () => {
                                        await Promise.all(
                                            selected.map(id =>
                                                deleteAttributeEntity(id)
                                            )
                                        );
                                        setSelected([]);
                                        void refresh();
                                    },
                                })
                            }
                        >
                            <Trash2Icon /> {t('common.delete', 'Delete')}
                        </Button>
                    </>
                ) : null}
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button
                            variant="ghost"
                            size="icon-sm"
                            className="ml-auto"
                        >
                            <MoreVerticalIcon />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        <DropdownMenuItem
                            onSelect={() =>
                                openModal(ExportEntitiesDialog, {
                                    list,
                                    locales: workspaceLocales,
                                })
                            }
                        >
                            <DownloadIcon /> {t('entity.export', 'Export…')}
                        </DropdownMenuItem>
                        <DropdownMenuItem
                            onSelect={() =>
                                openModal(ImportEntitiesDialog, {
                                    list,
                                    onImported: refresh,
                                })
                            }
                        >
                            <UploadIcon /> {t('entity.import', 'Import…')}
                        </DropdownMenuItem>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem
                            variant="destructive"
                            onSelect={() =>
                                openModal(ConfirmDialog, {
                                    title: t(
                                        'entity.clear.title',
                                        'Remove all values of "{{name}}"?',
                                        {name: list.name}
                                    ),
                                    destructive: true,
                                    textToType: list.name,
                                    onConfirm: async () => {
                                        await clearEntityList(list.id);
                                        void refresh();
                                    },
                                })
                            }
                        >
                            <EraserIcon /> {t('entity.clear', 'Clear list')}
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
            <div className="grid gap-4 lg:grid-cols-[2fr_1fr]">
                <div>
                    {entities.isLoading ? (
                        <Skeleton className="h-40" />
                    ) : (
                        <table className="w-full text-sm">
                            <thead className="text-xs text-muted-foreground">
                                <tr>
                                    <th className="w-8 px-2 py-1">
                                        <Checkbox
                                            checked={
                                                items.length > 0 &&
                                                selected.length === items.length
                                            }
                                            onCheckedChange={v =>
                                                setSelected(
                                                    v
                                                        ? items.map(i => i.id)
                                                        : []
                                                )
                                            }
                                        />
                                    </th>
                                    <th className="px-2 py-1 text-left">
                                        {t('entity.value', 'Value')}
                                    </th>
                                    <th className="px-2 py-1 text-left">
                                        {t('entity.status', 'Status')}
                                    </th>
                                    <th className="w-24" />
                                </tr>
                            </thead>
                            <tbody>
                                {items.map(e => (
                                    <tr
                                        key={e.id}
                                        className={cn(
                                            'border-t hover:bg-accent/40',
                                            editing !== 'new' &&
                                                editing?.id === e.id &&
                                                'bg-primary/5'
                                        )}
                                    >
                                        <td className="px-2 py-1">
                                            <Checkbox
                                                checked={selected.includes(
                                                    e.id
                                                )}
                                                onCheckedChange={v =>
                                                    setSelected(
                                                        v
                                                            ? [
                                                                  ...selected,
                                                                  e.id,
                                                              ]
                                                            : selected.filter(
                                                                  x =>
                                                                      x !== e.id
                                                              )
                                                    )
                                                }
                                            />
                                        </td>
                                        <td
                                            className="cursor-pointer px-2 py-1"
                                            onClick={() => edit(e)}
                                        >
                                            <EntityChip entity={e} size="sm" />
                                            {list.withSynonyms && e.synonyms ? (
                                                <span className="ml-2 text-xs text-muted-foreground">
                                                    {Object.values(e.synonyms)
                                                        .flat()
                                                        .join(', ')}
                                                </span>
                                            ) : null}
                                        </td>
                                        <td className="px-2 py-1">
                                            <Badge
                                                variant={
                                                    e.status ===
                                                    AttributeEntityStatus.Approved
                                                        ? 'success'
                                                        : e.status ===
                                                            AttributeEntityStatus.Pending
                                                          ? 'warning'
                                                          : 'destructive'
                                                }
                                            >
                                                {e.status ===
                                                AttributeEntityStatus.Approved
                                                    ? t(
                                                          'entity.approved',
                                                          'Approved'
                                                      )
                                                    : e.status ===
                                                        AttributeEntityStatus.Pending
                                                      ? t(
                                                            'entity.pending',
                                                            'Pending'
                                                        )
                                                      : t(
                                                            'entity.rejected',
                                                            'Rejected'
                                                        )}
                                            </Badge>
                                        </td>
                                        <td className="px-1 py-1 text-right whitespace-nowrap">
                                            {e.status !==
                                            AttributeEntityStatus.Approved ? (
                                                <Button
                                                    variant="ghost"
                                                    size="icon-xs"
                                                    onClick={() =>
                                                        setStatus(
                                                            e,
                                                            AttributeEntityStatus.Approved
                                                        )
                                                    }
                                                    aria-label={t(
                                                        'entity.approve',
                                                        'Approve'
                                                    )}
                                                >
                                                    <ThumbsUpIcon />
                                                </Button>
                                            ) : null}
                                            {e.status !==
                                            AttributeEntityStatus.Rejected ? (
                                                <Button
                                                    variant="ghost"
                                                    size="icon-xs"
                                                    onClick={() =>
                                                        setStatus(
                                                            e,
                                                            AttributeEntityStatus.Rejected
                                                        )
                                                    }
                                                    aria-label={t(
                                                        'entity.reject',
                                                        'Reject'
                                                    )}
                                                >
                                                    <ThumbsDownIcon />
                                                </Button>
                                            ) : null}
                                        </td>
                                    </tr>
                                ))}
                                {items.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={4}
                                            className="py-6 text-center text-muted-foreground"
                                        >
                                            {t('entity.empty', 'No value')}
                                        </td>
                                    </tr>
                                ) : null}
                            </tbody>
                        </table>
                    )}
                </div>
                <div className="rounded-md border p-3">
                    {editing ? (
                        <UnsavedChangesScope.Provider value={formScope}>
                            <EntityForm
                                key={editing === 'new' ? 'new' : editing.id}
                                entity={editing === 'new' ? undefined : editing}
                                list={list}
                                locales={workspaceLocales}
                                onSaved={saved => {
                                    // Stay on the saved value
                                    setEditing(saved);
                                    void refresh();
                                }}
                                onCancel={() => edit(null)}
                            />
                        </UnsavedChangesScope.Provider>
                    ) : (
                        <p className="text-sm text-muted-foreground">
                            {t(
                                'entity.select_hint',
                                'Select a value to edit it.'
                            )}
                        </p>
                    )}
                </div>
            </div>
        </div>
    );
}

function EntityForm({
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
    const {t} = useTranslation();
    const [value, setValue] = useState(entity?.value ?? '');
    const [status, setStatus] = useState(
        String(entity?.status ?? AttributeEntityStatus.Approved)
    );
    const [emoji, setEmoji] = useState(entity?.emoji ?? '');
    const [color, setColor] = useState(entity?.color ?? '');
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
        status,
        emoji,
        color,
        translations,
        synonyms,
    });

    const save = async () => {
        setSaving(true);
        try {
            const data: Partial<AttributeEntity> = {
                value,
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
            const saved = entity
                ? await putAttributeEntity(entity.id, data)
                : await postAttributeEntity(list.id, data);
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

function MergeEntitiesDialog({
    open,
    onOpenChange,
    resolve,
    entities,
    onMerged,
}: ModalProps<string> & {entities: AttributeEntity[]; onMerged: () => void}) {
    const {t} = useTranslation();
    const [kept, setKept] = useState(entities[0]?.id);

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title={t('entity.merge.title', 'Merge {{count}} values', {
                count: entities.length,
            })}
            submitLabel={t('entity.merge', 'Merge')}
            submitIcon={<MergeIcon />}
            canSubmit={!!kept}
            onSubmit={async () => {
                await mergeAttributeEntities(
                    kept!,
                    entities.map(e => e.id)
                );
                toast.success(t('entity.merged', 'Values merged'));
                onMerged();
                resolve?.(kept!);
            }}
        >
            <p className="mb-2 text-sm text-muted-foreground">
                {t(
                    'entity.merge.help',
                    'Select the value to keep; the others are replaced by it everywhere.'
                )}
            </p>
            <SimpleSelect
                value={kept}
                onValueChange={setKept}
                options={entities.map(e => ({
                    value: e.id,
                    label: e.value,
                }))}
            />
        </FormDialog>
    );
}

function ExportEntitiesDialog({
    open,
    onOpenChange,
    list,
    locales,
}: ModalProps & {list: EntityList; locales: string[]}) {
    const {t} = useTranslation();
    const [format, setFormat] = useState('csv');
    const [locale, setLocale] = useState('__all');

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title={t('entity.export.title', 'Export "{{name}}"', {
                name: list.name,
            })}
            submitLabel={t('entity.export', 'Export')}
            submitIcon={<DownloadIcon />}
            bodyClassName="space-y-3"
            onSubmit={async () => {
                const {blob, filename} = await exportEntityList(list.id, {
                    format,
                    locale: locale === '__all' ? undefined : locale,
                });
                downloadUrl(URL.createObjectURL(blob), filename);
            }}
        >
            <FormRow label={t('entity.export.format', 'Format')}>
                <SimpleSelect
                    value={format}
                    onValueChange={setFormat}
                    options={[
                        {value: 'csv', label: 'CSV'},
                        {value: 'json', label: 'JSON'},
                        {value: 'liform', label: 'LiForm (Uploader)'},
                    ]}
                />
            </FormRow>
            <FormRow label={t('entity.export.locale', 'Locale')}>
                <SimpleSelect
                    value={locale}
                    onValueChange={setLocale}
                    options={[
                        {
                            value: '__all',
                            label: t(
                                'entity.export.all_locales',
                                'All locales'
                            ),
                        },
                        ...locales.map(l => ({value: l, label: l})),
                    ]}
                />
            </FormRow>
        </FormDialog>
    );
}

function ImportEntitiesDialog({
    open,
    onOpenChange,
    list,
    onImported,
}: ModalProps & {list: EntityList; onImported: () => void}) {
    const {t} = useTranslation();
    const [data, setData] = useState('');

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            size="md"
            title={t('entity.import.title', 'Import into "{{name}}"', {
                name: list.name,
            })}
            submitLabel={t('entity.import', 'Import')}
            submitIcon={<UploadIcon />}
            canSubmit={!!data.trim()}
            dirty={!!data.trim()}
            bodyClassName="space-y-3"
            onSubmit={async () => {
                await importEntityList(list.id, 'csv', data);
                toast.success(t('entity.imported', 'Values imported'));
                onImported();
            }}
        >
            <Input
                type="file"
                accept=".csv,text/csv"
                onChange={async e => {
                    const f = e.target.files?.[0];
                    if (f) {
                        setData(await f.text());
                    }
                }}
            />
            <Textarea
                value={data}
                onChange={e => setData(e.target.value)}
                className="min-h-40 font-mono text-xs"
                placeholder={'value,locale,translation\n…'}
            />
        </FormDialog>
    );
}
