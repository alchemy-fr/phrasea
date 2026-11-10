'use client';

import {useTranslation} from 'react-i18next';
import {useQuery} from '@tanstack/react-query';
import {CloudUploadIcon} from 'lucide-react';
import type {AutomationActionConfig} from '@/types/api';
import {getRenditionDefinitions} from '@/lib/api/misc';
import {fetchAllPages} from '@/lib/api/hydra';
import {FormRow, Input} from '@alchemy/next-ui/ui/input';
import {Checkbox, LabeledControl, Switch} from '@alchemy/next-ui/ui/controls';
import {InlineLoader} from '@alchemy/next-ui/ui/loader';
import {
    describeRemoteDestination,
    isRemoteDestinationComplete,
    RemoteDestinationFields,
} from './RemoteDestinationFields';
import type {ActionDefinition, ActionFormProps} from './types';

export const defaultPathTemplate = '{collection}/{assetId}-{rendition}.{ext}';

const pathVariables = [
    'assetId',
    'title',
    'rendition',
    'ext',
    'originalName',
    'workspace',
    'collection',
    'date',
];

/**
 * Names of the rendition definitions the user can read, in all workspaces:
 * the export actions pick the renditions by name.
 */
async function getRenditionNames(): Promise<string[]> {
    const definitions = await fetchAllPages(
        getRenditionDefinitions({limit: 100}),
        url => getRenditionDefinitions({url})
    );

    return [...new Set(definitions.map(d => d.name))].sort((a, b) =>
        a.localeCompare(b)
    );
}

/**
 * The rendition names to choose from, with those of the configuration
 * kept visible even if no longer listed.
 */
export function useRenditionNames(selected: string[]) {
    const names = useQuery({
        queryKey: ['rendition-definitions', 'names'],
        queryFn: getRenditionNames,
    });

    return {
        isLoading: names.isLoading,
        names: [...new Set([...(names.data ?? []), ...selected])].sort((a, b) =>
            a.localeCompare(b)
        ),
    };
}

export function RemoteExportForm({value, onChange, errors}: ActionFormProps) {
    const {t} = useTranslation();
    const renditions: string[] = value.renditions ?? [];
    const names = useRenditionNames(renditions);

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
                    'automation.action.remote_export.renditions',
                    'Files to export'
                )}
                error={errors?.renditions}
                help={t(
                    'automation.action.remote_export.renditions_help',
                    'Renditions are chosen by name, in every workspace; a missing rendition is skipped. Without any rendition, the source file is exported.'
                )}
            >
                {names.isLoading ? <InlineLoader /> : null}
                <div className="max-h-48 space-y-1.5 overflow-y-auto">
                    {names.names.map(name => (
                        <LabeledControl key={name} label={name}>
                            <Checkbox
                                checked={renditions.includes(name)}
                                onCheckedChange={checked =>
                                    set({
                                        renditions: checked
                                            ? [...renditions, name]
                                            : renditions.filter(
                                                  n => n !== name
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
                    'automation.action.remote_export.path_template',
                    'File path'
                )}
                error={errors?.pathTemplate}
                help={t(
                    'automation.action.remote_export.path_template_help',
                    'Variables: {{variables}}. The rendition of the source file is "source".',
                    {
                        variables: pathVariables.map(v => `{${v}}`).join(', '),
                        interpolation: {escapeValue: false},
                    }
                )}
            >
                <Input
                    value={value.pathTemplate ?? ''}
                    placeholder={defaultPathTemplate}
                    className="font-mono"
                    onChange={e => set({pathTemplate: e.target.value})}
                />
            </FormRow>

            <LabeledControl
                label={t(
                    'automation.action.remote_export.overwrite',
                    'Overwrite existing files'
                )}
                description={t(
                    'automation.action.remote_export.overwrite_help',
                    'Otherwise an existing file is kept and counted as exported.'
                )}
            >
                <Switch
                    checked={!!value.overwrite}
                    onCheckedChange={overwrite => set({overwrite})}
                />
            </LabeledControl>
        </div>
    );
}

export const remoteExportAction: ActionDefinition = {
    label: t =>
        t('automation.action.remote_export.label', 'Export to S3, SFTP or FTP'),
    icon: <CloudUploadIcon />,
    Form: RemoteExportForm,
    summary: (config, t) => {
        const destination = describeRemoteDestination(config);

        return destination
            ? t(
                  'automation.action.remote_export.summary',
                  'Export to {{protocol}} ({{destination}})',
                  destination
              )
            : t(
                  'automation.action.remote_export.label',
                  'Export to S3, SFTP or FTP'
              );
    },
    defaultConfig: () => ({
        protocol: 's3',
        renditions: [],
        pathTemplate: defaultPathTemplate,
        overwrite: false,
    }),
    isComplete: isRemoteDestinationComplete,
};
