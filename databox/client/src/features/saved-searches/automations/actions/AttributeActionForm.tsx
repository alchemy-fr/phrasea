'use client';

import {useMemo} from 'react';
import {useTranslation} from 'react-i18next';
import {PencilLineIcon} from 'lucide-react';
import {
    AttributeType,
    type AttributeDefinition,
    type AutomationActionConfig,
} from '@/types/api';
import {useDefinitionsById} from '@/features/attributes/definitionsStore';
import {AttributeWidget} from '@/features/attributes/widgets/AttributeWidget';
import {
    AttributeDefinitionSelect,
    AttributeEntitySelect,
} from '@/components/form/selects';
import {TagsInput} from '@/components/form/TagsInput';
import {idFromIri} from '@/lib/utils/iri';
import {SimpleSelect} from '@alchemy/ui/ui/select';
import {FormRow, Input} from '@alchemy/ui/ui/input';
import {LabeledControl, Switch} from '@alchemy/ui/ui/controls';
import type {ActionDefinition, ActionFormProps} from './types';

type AttributeActionType = 'set' | 'add' | 'delete' | 'replace';

const DEFINITION_FILTERS = {enabled: true};

const isEditable = (d: AttributeDefinition) =>
    !!d.editable && d.canEdit !== false;

/**
 * Values a `delete` removes: only the attribute values equal to one of them
 * (AttributeEntity ids for an entity attribute), all of them when empty.
 */
export function deleteValuesOf(config: AutomationActionConfig): string[] {
    const value = config.value;
    if (Array.isArray(value)) {
        return value.filter(v => typeof v === 'string' && v !== '');
    }

    return typeof value === 'string' && value !== '' ? [value] : [];
}

function entityListIdOf(definition: AttributeDefinition): string | undefined {
    const list = definition.entityList;
    if (!list) {
        return undefined;
    }

    return typeof list === 'string' ? idFromIri(list) : list.id;
}

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
                    onValueChange={a => set({action: a, value: undefined})}
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
            {definition && action === 'delete' && definition.multiple ? (
                <FormRow
                    label={t(
                        'automation.action.attribute.delete_values',
                        'Values to remove'
                    )}
                    error={errors?.value}
                    help={t(
                        'automation.action.attribute.delete_values_help',
                        'Leave empty to remove all the values.'
                    )}
                >
                    {definition.type === AttributeType.Entity ? (
                        <AttributeEntitySelect
                            id={`automation-attr-${definition.id}`}
                            multiple
                            listId={entityListIdOf(definition)}
                            value={deleteValuesOf(value)}
                            onChange={v =>
                                set({value: v.length > 0 ? v : undefined})
                            }
                            placeholder={t(
                                'automation.action.attribute.delete_values_all',
                                'All the values'
                            )}
                        />
                    ) : (
                        <TagsInput
                            id={`automation-attr-${definition.id}`}
                            splitOn={/\n+/}
                            value={deleteValuesOf(value)}
                            onChange={v =>
                                set({value: v.length > 0 ? v : undefined})
                            }
                            placeholder={t(
                                'automation.action.attribute.delete_values_all',
                                'All the values'
                            )}
                        />
                    )}
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
            case 'delete': {
                const count = deleteValuesOf(config).length;

                return count > 0
                    ? t(
                          'automation.action.attribute.summary_delete_values',
                          'Remove {{count}} attribute values',
                          {count}
                      )
                    : t(
                          'automation.action.attribute.summary_delete',
                          'Remove attribute values'
                      );
            }
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
