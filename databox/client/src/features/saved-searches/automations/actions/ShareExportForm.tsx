'use client';

import {useTranslation} from 'react-i18next';
import {useQuery} from '@tanstack/react-query';
import {FileSpreadsheetIcon} from 'lucide-react';
import type {AutomationActionConfig} from '@/types/api';
import {getAttributeDefinitionsPage} from '@/lib/api/metadata';
import {getRenditionDefinitions} from '@/lib/api/misc';
import {fetchAllPages} from '@/lib/api/hydra';
import {FieldError, FormRow, Input} from '@alchemy/next-ui/ui/input';
import {Checkbox, LabeledControl, Switch} from '@alchemy/next-ui/ui/controls';
import {InlineLoader} from '@alchemy/next-ui/ui/loader';
import {
    describeRemoteDestination,
    isRemoteDestinationComplete,
    RemoteDestinationFields,
} from './RemoteDestinationFields';
import type {ActionDefinition, ActionFormProps} from './types';

/** Same bounds and defaults as ShareExportAction in the API */
export const defaultReportPathTemplate =
    '{savedSearch}/{automation}-{datetime}.xlsx';
export const MAX_ATTRIBUTES = 50;

const reportPathVariables = [
    'automation',
    'savedSearch',
    'date',
    'datetime',
    'runId',
];

type Choice = {id: string; label: string; description?: string};

const byLabel = (a: Choice, b: Choice) => a.label.localeCompare(b.label);

/**
 * The rendition definitions of the workspace: the action picks them by ID.
 */
async function getRenditionChoices(workspaceId: string): Promise<Choice[]> {
    const definitions = await fetchAllPages(
        getRenditionDefinitions({workspaceId, limit: 100}),
        url => getRenditionDefinitions({url})
    );

    return definitions.map(d => ({id: d.id, label: d.name})).sort(byLabel);
}

/**
 * The attribute definitions of the workspace the user can read: the action picks them by ID.
 */
async function getAttributeChoices(workspaceId: string): Promise<Choice[]> {
    const definitions = await fetchAllPages(
        getAttributeDefinitionsPage({workspaceId, limit: 100}),
        url => getAttributeDefinitionsPage({url})
    );

    return definitions
        .map(d => ({
            id: d.id,
            label: d.displayName || d.name,
            description: d.slug ?? undefined,
        }))
        .sort(byLabel);
}

/**
 * The listed choices, with the IDs of the configuration kept visible even if no longer listed.
 */
function withSelected(
    listed: Choice[] | undefined,
    selected: string[]
): Choice[] {
    const choices = listed ?? [];

    return [
        ...choices,
        ...selected
            .filter(id => !choices.some(c => c.id === id))
            .map(id => ({id, label: id})),
    ];
}

function toggleItem(list: string[], item: string, checked: boolean): string[] {
    return checked ? [...list, item] : list.filter(i => i !== item);
}

export function ShareExportForm({
    value,
    onChange,
    errors,
    workspaceId,
}: ActionFormProps) {
    const {t} = useTranslation();
    const renditionIds: string[] = value.renditionIds ?? [];
    const attributeIds: string[] = value.attributeIds ?? [];

    const renditionChoices = useQuery({
        queryKey: ['rendition-definitions', 'automation', workspaceId],
        queryFn: () => getRenditionChoices(workspaceId!),
        enabled: !!workspaceId,
    });
    const attributeChoices = useQuery({
        queryKey: ['attribute-definitions', 'automation', workspaceId],
        queryFn: () => getAttributeChoices(workspaceId!),
        enabled: !!workspaceId,
    });
    const allRenditions = withSelected(renditionChoices.data, renditionIds);
    const allAttributes = withSelected(attributeChoices.data, attributeIds);

    const set = (patch: AutomationActionConfig) =>
        onChange({...value, ...patch});

    return (
        <div>
            <RemoteDestinationFields
                value={value}
                onChange={onChange}
                errors={errors}
            />

            <FormRow
                label={t(
                    'automation.action.share_export.path_template',
                    'File path'
                )}
                error={errors?.pathTemplate}
                help={t(
                    'automation.action.share_export.path_template_help',
                    'Variables: {{variables}}. An existing file is replaced.',
                    {
                        variables: reportPathVariables
                            .map(v => `{${v}}`)
                            .join(', '),
                        interpolation: {escapeValue: false},
                    }
                )}
            >
                <Input
                    value={value.pathTemplate ?? ''}
                    placeholder={defaultReportPathTemplate}
                    className="font-mono"
                    onChange={e => set({pathTemplate: e.target.value})}
                />
            </FormRow>

            {workspaceId ? (
                <>
                    <FormRow
                        label={t(
                            'automation.action.share_export.renditions',
                            'Shared renditions'
                        )}
                        error={errors?.renditionIds}
                        help={t(
                            'automation.action.share_export.renditions_help',
                            'One column of links per rendition of the workspace. Only those of a public policy are shared.'
                        )}
                    >
                        {renditionChoices.isLoading ? <InlineLoader /> : null}
                        <div className="max-h-48 space-y-1.5 overflow-y-auto">
                            {allRenditions.map(({id, label}) => (
                                <LabeledControl key={id} label={label}>
                                    <Checkbox
                                        checked={renditionIds.includes(id)}
                                        onCheckedChange={checked =>
                                            set({
                                                renditionIds: toggleItem(
                                                    renditionIds,
                                                    id,
                                                    !!checked
                                                ),
                                            })
                                        }
                                    />
                                </LabeledControl>
                            ))}
                        </div>
                    </FormRow>

                    <FormRow
                        label={t(
                            'automation.action.share_export.attributes',
                            'Attribute columns'
                        )}
                        error={errors?.attributeIds}
                        help={t(
                            'automation.action.share_export.attributes_help',
                            'Optional, {{max}} at most, among the attributes of the workspace.',
                            {max: MAX_ATTRIBUTES}
                        )}
                    >
                        {attributeChoices.isLoading ? <InlineLoader /> : null}
                        <div className="max-h-48 space-y-1.5 overflow-y-auto">
                            {allAttributes.map(({id, label, description}) => (
                                <LabeledControl
                                    key={id}
                                    label={label}
                                    description={
                                        description && description !== label
                                            ? description
                                            : undefined
                                    }
                                >
                                    <Checkbox
                                        checked={attributeIds.includes(id)}
                                        disabled={
                                            !attributeIds.includes(id) &&
                                            attributeIds.length >=
                                                MAX_ATTRIBUTES
                                        }
                                        onCheckedChange={checked =>
                                            set({
                                                attributeIds: toggleItem(
                                                    attributeIds,
                                                    id,
                                                    !!checked
                                                ),
                                            })
                                        }
                                    />
                                </LabeledControl>
                            ))}
                        </div>
                    </FormRow>
                </>
            ) : (
                <div className="mb-4">
                    <p className="text-sm text-muted-foreground">
                        {t(
                            'automation.action.share_export.no_workspace',
                            'Choose the workspace of the automation first: the renditions and attributes are those of this workspace.'
                        )}
                    </p>
                    <FieldError>{errors?.workspace}</FieldError>
                </div>
            )}

            {attributeIds.length > 0 ? (
                <FormRow
                    label={t(
                        'automation.action.share_export.locale',
                        'Locale of the attributes'
                    )}
                    error={errors?.locale}
                    help={t(
                        'automation.action.share_export.locale_help',
                        'For the translated attributes. By default, the locales of the workspace.'
                    )}
                >
                    <Input
                        value={value.locale ?? ''}
                        placeholder="en"
                        className="max-w-32"
                        onChange={e => set({locale: e.target.value || null})}
                    />
                </FormRow>
            ) : null}

            <LabeledControl
                label={t(
                    'automation.action.share_export.upload_when_empty',
                    'Upload even without any asset'
                )}
                description={t(
                    'automation.action.share_export.upload_when_empty_help',
                    'Otherwise no file is uploaded when the run handled no asset.'
                )}
            >
                <Switch
                    checked={!!value.uploadWhenEmpty}
                    onCheckedChange={uploadWhenEmpty => set({uploadWhenEmpty})}
                />
            </LabeledControl>
        </div>
    );
}

export const shareExportAction: ActionDefinition = {
    label: t =>
        t(
            'automation.action.share_export.label',
            'Share and export the links (XLSX)'
        ),
    icon: <FileSpreadsheetIcon />,
    Form: ShareExportForm,
    summary: (config, t) => {
        const destination = describeRemoteDestination(config);

        return destination
            ? t(
                  'automation.action.share_export.summary',
                  'Share and export the links to {{protocol}} ({{destination}})',
                  destination
              )
            : t(
                  'automation.action.share_export.label',
                  'Share and export the links (XLSX)'
              );
    },
    defaultConfig: () => ({
        protocol: 's3',
        renditionIds: [],
        attributeIds: [],
        locale: null,
        pathTemplate: defaultReportPathTemplate,
        uploadWhenEmpty: false,
    }),
    isComplete: (config, context) =>
        !!context?.workspaceId &&
        isRemoteDestinationComplete(config) &&
        (config.renditionIds?.length ?? 0) > 0 &&
        (config.attributeIds?.length ?? 0) <= MAX_ATTRIBUTES,
    // The renditions and attributes are definitions of the workspace
    onWorkspaceChange: config => ({
        ...config,
        renditionIds: [],
        attributeIds: [],
    }),
};
