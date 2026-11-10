'use client';

import {useTranslation} from 'react-i18next';
import {useQuery} from '@tanstack/react-query';
import {FileSpreadsheetIcon} from 'lucide-react';
import type {AutomationActionConfig} from '@/types/api';
import {getAttributeDefinitionsPage} from '@/lib/api/metadata';
import {fetchAllPages} from '@/lib/api/hydra';
import {FormRow, Input} from '@alchemy/next-ui/ui/input';
import {Checkbox, LabeledControl, Switch} from '@alchemy/next-ui/ui/controls';
import {InlineLoader} from '@alchemy/next-ui/ui/loader';
import {
    describeRemoteDestination,
    isRemoteDestinationComplete,
    RemoteDestinationFields,
} from './RemoteDestinationFields';
import {useRenditionNames} from './RemoteExportForm';
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

type AttributeChoice = {slug: string; label: string};

/**
 * The attribute definitions the user can read, in all workspaces, by slug:
 * the action picks the attributes by slug.
 */
async function getAttributeChoices(): Promise<AttributeChoice[]> {
    const definitions = await fetchAllPages(
        getAttributeDefinitionsPage({limit: 100}),
        url => getAttributeDefinitionsPage({url})
    );

    const bySlug = new Map<string, string>();
    for (const d of definitions) {
        if (d.slug && !bySlug.has(d.slug)) {
            bySlug.set(d.slug, d.displayName || d.name);
        }
    }

    return [...bySlug.entries()]
        .map(([slug, label]) => ({slug, label}))
        .sort((a, b) => a.label.localeCompare(b.label));
}

function toggleItem(list: string[], item: string, checked: boolean): string[] {
    return checked ? [...list, item] : list.filter(i => i !== item);
}

export function ShareExportForm({value, onChange, errors}: ActionFormProps) {
    const {t} = useTranslation();
    const renditions: string[] = value.renditions ?? [];
    const attributes: string[] = value.attributes ?? [];
    const renditionNames = useRenditionNames(renditions);

    const attributeChoices = useQuery({
        queryKey: ['attribute-definitions', 'slugs'],
        queryFn: getAttributeChoices,
    });
    // Keep the slugs of the configuration visible, even if no longer listed
    const listed = attributeChoices.data ?? [];
    const allAttributes: AttributeChoice[] = [
        ...listed,
        ...attributes
            .filter(slug => !listed.some(c => c.slug === slug))
            .map(slug => ({slug, label: slug})),
    ];

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

            <FormRow
                label={t(
                    'automation.action.share_export.renditions',
                    'Shared renditions'
                )}
                error={errors?.renditions}
                help={t(
                    'automation.action.share_export.renditions_help',
                    'One column of links per rendition. Renditions are chosen by name, in every workspace; only those of a public policy are shared.'
                )}
            >
                {renditionNames.isLoading ? <InlineLoader /> : null}
                <div className="max-h-48 space-y-1.5 overflow-y-auto">
                    {renditionNames.names.map(name => (
                        <LabeledControl key={name} label={name}>
                            <Checkbox
                                checked={renditions.includes(name)}
                                onCheckedChange={checked =>
                                    set({
                                        renditions: toggleItem(
                                            renditions,
                                            name,
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
                error={errors?.attributes}
                help={t(
                    'automation.action.share_export.attributes_help',
                    'Optional, {{max}} at most. Attributes are chosen by slug, in every workspace.',
                    {max: MAX_ATTRIBUTES}
                )}
            >
                {attributeChoices.isLoading ? <InlineLoader /> : null}
                <div className="max-h-48 space-y-1.5 overflow-y-auto">
                    {allAttributes.map(({slug, label}) => (
                        <LabeledControl
                            key={slug}
                            label={label}
                            description={label !== slug ? slug : undefined}
                        >
                            <Checkbox
                                checked={attributes.includes(slug)}
                                disabled={
                                    !attributes.includes(slug) &&
                                    attributes.length >= MAX_ATTRIBUTES
                                }
                                onCheckedChange={checked =>
                                    set({
                                        attributes: toggleItem(
                                            attributes,
                                            slug,
                                            !!checked
                                        ),
                                    })
                                }
                            />
                        </LabeledControl>
                    ))}
                </div>
            </FormRow>

            {attributes.length > 0 ? (
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
        renditions: [],
        attributes: [],
        locale: null,
        pathTemplate: defaultReportPathTemplate,
        uploadWhenEmpty: false,
    }),
    isComplete: config =>
        isRemoteDestinationComplete(config) &&
        (config.renditions?.length ?? 0) > 0 &&
        (config.attributes?.length ?? 0) <= MAX_ATTRIBUTES,
};
