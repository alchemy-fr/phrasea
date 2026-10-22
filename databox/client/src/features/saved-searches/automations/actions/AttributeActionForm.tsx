'use client';

import {useMemo} from 'react';
import {useTranslation} from 'react-i18next';
import {PencilLineIcon} from 'lucide-react';
import type {AttributeDefinition} from '@/types/api';
import {
    useDefinitionsById,
    workspaceIdOf,
} from '@/features/attributes/definitionsStore';
import {AttributeWidget} from '@/features/attributes/widgets/AttributeWidget';
import {AttributeDefinitionSelect} from '@/components/form/selects';
import {SimpleSelect} from '@/components/ui/select';
import {FormRow, Input} from '@/components/ui/input';
import {LabeledControl, Switch} from '@/components/ui/controls';
import type {ActionDefinition, ActionFormProps} from './types';

type AttributeActionType = 'set' | 'add' | 'delete' | 'replace';

const DEFINITION_FILTERS = {enabled: true};

const isEditable = (d: AttributeDefinition) =>
    !!d.editable && d.canEdit !== false;

export function AttributeActionForm({
    value,
    onChange,
    errors,
}: ActionFormProps) {
    const {t} = useTranslation();
    const ids = useMemo(
        () => (value.definitionId ? [value.definitionId] : []),
        [value.definitionId]
    );
    const byId = useDefinitionsById({ids, includeBuiltIn: false});
    const definition = value.definitionId
        ? (byId[value.definitionId] as AttributeDefinition | undefined)
        : undefined;
    const action: AttributeActionType = value.action ?? 'set';

    const set = (patch: Record<string, unknown>) =>
        onChange({...value, ...patch});

    const actions: {value: AttributeActionType; label: string}[] = [
        {
            value: 'set',
            label: t('automation.action.attribute.set', 'Set the value'),
        },
        ...(definition?.multiple
            ? [
                  {
                      value: 'add' as const,
                      label: t(
                          'automation.action.attribute.add',
                          'Add a value'
                      ),
                  },
              ]
            : []),
        {
            value: 'delete',
            label: t('automation.action.attribute.delete', 'Remove the values'),
        },
        {
            value: 'replace',
            label: t('automation.action.attribute.replace', 'Replace text'),
        },
    ];

    return (
        <div>
            <FormRow
                label={t('automation.action.attribute.definition', 'Attribute')}
                error={errors?.definitionId}
                help={t(
                    'automation.action.attribute.definition_help',
                    'Only the assets of the attribute workspace are changed.'
                )}
            >
                <AttributeDefinitionSelect
                    filters={DEFINITION_FILTERS}
                    postFilter={isEditable}
                    showWorkspace
                    value={value.definitionId}
                    onChange={definitionId =>
                        set({definitionId, value: undefined})
                    }
                    placeholder={t(
                        'automation.action.attribute.definition_placeholder',
                        'Choose an attribute…'
                    )}
                />
            </FormRow>
            <FormRow
                label={t('automation.action.attribute.action', 'Change')}
                error={errors?.action}
            >
                <SimpleSelect<AttributeActionType>
                    value={action}
                    onValueChange={a => set({action: a})}
                    options={actions}
                />
            </FormRow>
            {definition?.translatable ? (
                <FormRow
                    label={t('automation.action.attribute.locale', 'Locale')}
                    error={errors?.locale}
                >
                    <Input
                        value={value.locale ?? ''}
                        placeholder="en"
                        onChange={e =>
                            set({locale: e.target.value.trim() || null})
                        }
                    />
                </FormRow>
            ) : null}
            {definition && (action === 'set' || action === 'add') ? (
                <FormRow
                    label={t('automation.action.attribute.value', 'Value')}
                    error={errors?.value}
                >
                    <AttributeWidget
                        id={`automation-attr-${definition.id}`}
                        definition={definition}
                        workspaceId={workspaceIdOf(definition)}
                        value={
                            Array.isArray(value.value)
                                ? value.value[0]
                                : value.value
                        }
                        onChange={v =>
                            set({
                                value:
                                    action === 'set' && definition.multiple
                                        ? v === undefined ||
                                          v === null ||
                                          v === ''
                                            ? null
                                            : [v]
                                        : v,
                            })
                        }
                    />
                </FormRow>
            ) : null}
            {action === 'replace' ? (
                <>
                    <FormRow
                        label={t(
                            'automation.action.attribute.search',
                            'Text to replace'
                        )}
                        error={errors?.value}
                    >
                        <Input
                            value={value.value ?? ''}
                            onChange={e => set({value: e.target.value})}
                        />
                    </FormRow>
                    <FormRow
                        label={t(
                            'automation.action.attribute.replace_with',
                            'Replace with'
                        )}
                        error={errors?.replaceWith}
                    >
                        <Input
                            value={value.replaceWith ?? ''}
                            onChange={e => set({replaceWith: e.target.value})}
                        />
                    </FormRow>
                    <LabeledControl
                        label={t(
                            'automation.action.attribute.regex',
                            'Regular expression'
                        )}
                    >
                        <Switch
                            checked={!!value.regex}
                            onCheckedChange={regex => set({regex})}
                        />
                    </LabeledControl>
                </>
            ) : null}
        </div>
    );
}

export const attributeAction: ActionDefinition = {
    label: t => t('automation.action.attribute.label', 'Change an attribute'),
    icon: <PencilLineIcon />,
    Form: AttributeActionForm,
    summary: (config, t) => {
        switch (config.action) {
            case 'add':
                return t(
                    'automation.action.attribute.summary_add',
                    'Add an attribute value'
                );
            case 'delete':
                return t(
                    'automation.action.attribute.summary_delete',
                    'Remove attribute values'
                );
            case 'replace':
                return t(
                    'automation.action.attribute.summary_replace',
                    'Replace text in an attribute'
                );
            default:
                return t(
                    'automation.action.attribute.summary_set',
                    'Set an attribute value'
                );
        }
    },
    defaultConfig: () => ({action: 'set'}),
    isComplete: config =>
        !!config.definitionId &&
        (config.action !== 'replace' || !!config.value) &&
        (config.action !== 'add' ||
            (config.value !== undefined &&
                config.value !== null &&
                config.value !== '')),
};
