'use client';

import {useMemo, useRef, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {
    ArrowDownIcon,
    ArrowUpIcon,
    PlusIcon,
    SaveIcon,
    Trash2Icon,
} from 'lucide-react';
import {toast} from 'sonner';
import {
    AutomationActionConfig,
    AutomationScope,
    SavedSearchAutomation,
} from '@/types/api';
import {
    patchSavedSearchAutomation,
    postSavedSearchAutomation,
    resetAutomationCursor,
    SavedSearchAutomationInput,
} from '@/lib/api/automations';
import {isApiError} from '@/lib/api/http';
import type {ModalProps} from '@/components/modals/ModalProvider';
import {FormDialog} from '@/components/modals/FormDialog';
import {FieldError, FormRow, Input} from '@/components/ui/input';
import {SimpleSelect} from '@/components/ui/select';
import {
    Checkbox,
    LabeledControl,
    RadioGroup,
    RadioGroupItem,
    Switch,
} from '@/components/ui/controls';
import {Button} from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/menu';
import {toastError} from '@/lib/utils/errors';
import {formatDateTime} from '@/lib/utils/format';
import {
    actionTypes,
    getActionDefinition,
    type ActionType,
} from './actions/registry';
import {AutomationFormErrors, mapViolations} from './formErrors';
import {
    changePresetKind,
    cronToPreset,
    formatTime,
    parseTime,
    presetToCron,
    SchedulePreset,
    SchedulePresetKind,
    weekdayLabels,
} from './schedule';

type ActionItem = {
    key: number;
    type: string;
    config: AutomationActionConfig;
};

const noErrors: AutomationFormErrors = {fields: {}, actions: {}};

function browserTimezone(): string {
    try {
        return Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC';
    } catch {
        return 'UTC';
    }
}

function timezones(): string[] {
    const list: string[] =
        (
            Intl as unknown as {supportedValuesOf?: (key: string) => string[]}
        ).supportedValuesOf?.('timeZone') ?? [];

    return ['UTC', ...list.filter(tz => tz !== 'UTC')];
}

type Props = ModalProps<SavedSearchAutomation> & {
    savedSearchId: string;
    automation?: SavedSearchAutomation;
};

export function AutomationFormDialog({
    open,
    onOpenChange,
    resolve,
    savedSearchId,
    automation,
}: Props) {
    const {t, i18n} = useTranslation();
    const keySeq = useRef(0);
    const [name, setName] = useState(automation?.name ?? '');
    const [enabled, setEnabled] = useState(automation?.enabled ?? true);
    const [preset, setPreset] = useState<SchedulePreset>(() =>
        cronToPreset(automation?.schedule)
    );
    const [timezone, setTimezone] = useState(
        automation?.timezone ?? browserTimezone()
    );
    const [scope, setScope] = useState<AutomationScope>(
        automation?.scope ?? AutomationScope.New
    );
    const [ignoreExisting, setIgnoreExisting] = useState(false);
    const [actions, setActions] = useState<ActionItem[]>(() =>
        (automation?.actions ?? []).map(a => ({
            key: keySeq.current++,
            type: a.type,
            config: Array.isArray(a.config) ? {} : a.config,
        }))
    );
    const [errors, setErrors] = useState<AutomationFormErrors>(noErrors);
    const timezoneOptions = useMemo(
        () => timezones().map(tz => ({value: tz, label: tz})),
        []
    );

    const schedule = presetToCron(preset);
    const scheduleUnchanged = (automation?.schedule ?? null) === schedule;
    const canSubmit =
        !!name.trim() &&
        actions.length > 0 &&
        actions.every(
            a => getActionDefinition(a.type)?.isComplete(a.config) ?? true
        ) &&
        (preset.kind !== 'custom' || !!preset.expression.trim());

    const addAction = (type: ActionType) =>
        setActions(list => [
            ...list,
            {
                key: keySeq.current++,
                type,
                config: getActionDefinition(type)!.defaultConfig(),
            },
        ]);
    const updateAction = (key: number, config: AutomationActionConfig) =>
        setActions(list => list.map(a => (a.key === key ? {...a, config} : a)));
    const removeAction = (key: number) =>
        setActions(list => list.filter(a => a.key !== key));
    const moveAction = (index: number, delta: number) =>
        setActions(list => {
            const next = [...list];
            const [item] = next.splice(index, 1);
            next.splice(index + delta, 0, item);

            return next;
        });

    const submit = async () => {
        const data: SavedSearchAutomationInput = {
            name: name.trim(),
            enabled,
            schedule: schedule ?? '',
            timezone,
            scope,
            actions: actions.map(({type, config}) => ({type, config})),
        };

        try {
            let result: SavedSearchAutomation;
            if (automation) {
                result = await patchSavedSearchAutomation(automation.id, data);
            } else {
                result = await postSavedSearchAutomation({
                    ...data,
                    savedSearch: savedSearchId,
                });
                if (scope === AutomationScope.New && ignoreExisting) {
                    await resetAutomationCursor(result.id, 'current');
                }
            }
            setErrors(noErrors);
            toast.success(
                automation
                    ? t('automation.updated', 'Automation updated')
                    : t('automation.created', 'Automation created')
            );
            resolve?.(result);
        } catch (e) {
            if (isApiError(e, 422)) {
                setErrors(mapViolations(e.violations));
            }
            toastError(e);

            return false;
        }
    };

    const weekdays = weekdayLabels(t);
    const kinds: {value: SchedulePresetKind; label: string}[] = [
        {
            value: 'manual',
            label: t('automation.schedule.kind.manual', 'On demand only'),
        },
        {
            value: 'hourly',
            label: t('automation.schedule.kind.hourly', 'Every hour'),
        },
        {
            value: 'daily',
            label: t('automation.schedule.kind.daily', 'Every day'),
        },
        {
            value: 'weekly',
            label: t('automation.schedule.kind.weekly', 'Every week'),
        },
        {
            value: 'custom',
            label: t(
                'automation.schedule.kind.custom',
                'Custom (cron expression)'
            ),
        },
    ];

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            size="lg"
            title={
                automation
                    ? t('automation.edit.title', 'Edit automation')
                    : t('automation.create.title', 'New automation')
            }
            submitLabel={t('common.save', 'Save')}
            submitIcon={<SaveIcon />}
            canSubmit={canSubmit}
            dirty
            onSubmit={submit}
        >
            <FormRow
                label={t('common.name', 'Name')}
                error={errors.fields.name}
            >
                <Input
                    autoFocus
                    value={name}
                    onChange={e => setName(e.target.value)}
                />
            </FormRow>
            <LabeledControl
                className="mb-4"
                label={t('automation.enabled', 'Enabled')}
                description={t(
                    'automation.enabled_help',
                    'A disabled automation is not scheduled, but can still be run on demand.'
                )}
            >
                <Switch checked={enabled} onCheckedChange={setEnabled} />
            </LabeledControl>

            <FormRow
                label={t('automation.schedule.label', 'Trigger')}
                error={errors.fields.schedule}
                help={
                    automation?.nextRunAt && scheduleUnchanged
                        ? t(
                              'automation.schedule.next_run',
                              'Next run: {{date}}',
                              {
                                  date: formatDateTime(
                                      automation.nextRunAt,
                                      'medium',
                                      i18n.language
                                  ),
                              }
                          )
                        : preset.kind !== 'manual'
                          ? t(
                                'automation.schedule.help',
                                'Automations can be run at most every 5 minutes. They can always be run on demand.'
                            )
                          : undefined
                }
            >
                <div className="flex flex-wrap items-center gap-2">
                    <SimpleSelect<SchedulePresetKind>
                        className="w-56"
                        value={preset.kind}
                        onValueChange={kind =>
                            setPreset(p => changePresetKind(p, kind))
                        }
                        options={kinds}
                    />
                    {preset.kind === 'hourly' ? (
                        <LabeledControl
                            label={t('automation.schedule.minute', 'at minute')}
                        >
                            <Input
                                type="number"
                                min={0}
                                max={59}
                                className="w-20"
                                value={preset.minute}
                                onChange={e =>
                                    setPreset({
                                        kind: 'hourly',
                                        minute: Math.min(
                                            59,
                                            Math.max(
                                                0,
                                                parseInt(e.target.value, 10) ||
                                                    0
                                            )
                                        ),
                                    })
                                }
                            />
                        </LabeledControl>
                    ) : null}
                    {preset.kind === 'weekly' ? (
                        <SimpleSelect<string>
                            className="w-40"
                            value={String(preset.weekday)}
                            onValueChange={d =>
                                setPreset({...preset, weekday: parseInt(d, 10)})
                            }
                            options={weekdays.map((label, i) => ({
                                value: String(i),
                                label,
                            }))}
                        />
                    ) : null}
                    {preset.kind === 'daily' || preset.kind === 'weekly' ? (
                        <Input
                            type="time"
                            className="w-32"
                            value={formatTime(preset.hour, preset.minute)}
                            onChange={e => {
                                const time = parseTime(e.target.value);
                                if (time) {
                                    setPreset({...preset, ...time});
                                }
                            }}
                        />
                    ) : null}
                    {preset.kind === 'custom' ? (
                        <Input
                            className="w-56 font-mono"
                            placeholder="*/15 * * * *"
                            value={preset.expression}
                            onChange={e =>
                                setPreset({
                                    kind: 'custom',
                                    expression: e.target.value,
                                })
                            }
                        />
                    ) : null}
                </div>
            </FormRow>
            {preset.kind !== 'manual' ? (
                <FormRow
                    label={t('automation.timezone', 'Timezone')}
                    error={errors.fields.timezone}
                >
                    <SimpleSelect
                        value={timezone}
                        onValueChange={setTimezone}
                        options={timezoneOptions}
                    />
                </FormRow>
            ) : null}

            <FormRow
                label={t('automation.scope.label', 'Assets')}
                error={errors.fields.scope}
            >
                <RadioGroup
                    value={scope}
                    onValueChange={v => setScope(v as AutomationScope)}
                    className="space-y-2"
                >
                    <LabeledControl
                        label={t('automation.scope.new', 'New results only')}
                        description={t(
                            'automation.scope.new_help',
                            'The results not handled by a previous run (an asset failing is retried at the next run).'
                        )}
                    >
                        <RadioGroupItem value={AutomationScope.New} />
                    </LabeledControl>
                    <LabeledControl
                        label={t('automation.scope.all', 'All results')}
                        description={t(
                            'automation.scope.all_help',
                            'Every result of the search, at each run.'
                        )}
                    >
                        <RadioGroupItem value={AutomationScope.All} />
                    </LabeledControl>
                </RadioGroup>
            </FormRow>
            {!automation && scope === AutomationScope.New ? (
                <LabeledControl
                    className="mb-4"
                    label={t(
                        'automation.ignore_existing',
                        'Ignore the existing assets'
                    )}
                    description={t(
                        'automation.ignore_existing_help',
                        'The current results are marked as handled: only the assets entering the search from now on will be processed.'
                    )}
                >
                    <Checkbox
                        checked={ignoreExisting}
                        onCheckedChange={c => setIgnoreExisting(c === true)}
                    />
                </LabeledControl>
            ) : null}

            <div className="space-y-3">
                <div className="flex items-center justify-between">
                    <h3 className="text-sm font-medium">
                        {t('automation.actions', 'Actions')}
                    </h3>
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button variant="outline" size="sm">
                                <PlusIcon />
                                {t('automation.add_action', 'Add an action')}
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            {actionTypes.map(type => {
                                const definition = getActionDefinition(type)!;

                                return (
                                    <DropdownMenuItem
                                        key={type}
                                        onSelect={() => addAction(type)}
                                    >
                                        {definition.icon}
                                        {definition.label(t)}
                                    </DropdownMenuItem>
                                );
                            })}
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
                <FieldError>{errors.fields.actions}</FieldError>
                {actions.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        {t(
                            'automation.no_action',
                            'Add the actions to apply to the assets, in order.'
                        )}
                    </p>
                ) : null}
                {actions.map((action, i) => {
                    const definition = getActionDefinition(action.type);
                    const Form = definition?.Form;

                    return (
                        <div
                            key={action.key}
                            className="rounded-md border p-3"
                            data-testid="automation-action"
                        >
                            <div className="mb-3 flex items-center gap-2 text-sm font-medium [&>svg]:size-4">
                                <span className="text-muted-foreground">
                                    {i + 1}.
                                </span>
                                {definition?.icon}
                                <span className="flex-1">
                                    {definition?.label(t) ?? action.type}
                                </span>
                                <Button
                                    variant="ghost"
                                    size="icon-xs"
                                    disabled={i === 0}
                                    onClick={() => moveAction(i, -1)}
                                    title={t('common.move_up', 'Move up')}
                                >
                                    <ArrowUpIcon />
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="icon-xs"
                                    disabled={i === actions.length - 1}
                                    onClick={() => moveAction(i, 1)}
                                    title={t('common.move_down', 'Move down')}
                                >
                                    <ArrowDownIcon />
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="icon-xs"
                                    onClick={() => removeAction(action.key)}
                                    title={t('common.remove', 'Remove')}
                                >
                                    <Trash2Icon />
                                </Button>
                            </div>
                            {Form ? (
                                <Form
                                    value={action.config}
                                    onChange={config =>
                                        updateAction(action.key, config)
                                    }
                                    errors={errors.actions[i]}
                                />
                            ) : (
                                <p className="text-xs text-muted-foreground">
                                    {t(
                                        'automation.unknown_action',
                                        'This action cannot be edited here.'
                                    )}
                                </p>
                            )}
                            <FieldError>{errors.actions[i]?.type}</FieldError>
                        </div>
                    );
                })}
            </div>
        </FormDialog>
    );
}
