import {IntegrationType, Workspace, WorkspaceIntegration} from '../../../types';
import {
    Alert,
    Button,
    FormGroup,
    FormHelperText,
    FormLabel,
    InputLabel,
    ListItemText,
    MenuList,
    TextField,
    Typography,
} from '@mui/material';
import {CheckboxWidget, FormFieldErrors, FormRow} from '@alchemy/react-form';
import DefinitionManager, {
    DefinitionItemFormProps,
    DefinitionItemProps,
} from './DefinitionManager/DefinitionManager.tsx';
import {useTranslation} from 'react-i18next';
import CodeEditorWidget from '../../Form/CodeEditorWidget.tsx';
import {
    deleteIntegration,
    getIntegrationType,
    getWorkspaceIntegrations,
    postIntegration,
    putIntegration,
} from '../../../api/integrations.ts';
import React, {useEffect, useMemo, useState} from 'react';
import IntegrationTypeSelect from '../../Form/IntegrationTypeSelect.tsx';
import CodeEditor from '../../Media/Asset/Widgets/CodeEditor.tsx';
import ContentCopyIcon from '@mui/icons-material/ContentCopy';
import LastErrorsList from './LastErrorsList.tsx';
import {DataTabProps} from '../Tabbed/TabbedDialog.tsx';
import WorkspaceIntegrationSelect from '../../Form/WorkspaceIntegrationSelect.tsx';
import InfoRow from '../Info/InfoRow.tsx';
import {search} from '../../../lib/search.ts';
import AclForm from '../../Permissions/AclForm.tsx';
import {
    AclPermission,
    PermissionObject,
    PermissionType,
} from '../../Permissions/permissionsTypes.ts';

function Item({
    usedFormSubmit,
    data,
    workspace,
}: DefinitionItemFormProps<WorkspaceIntegration>) {
    const {t} = useTranslation();
    const [integrationHelp, setIntegrationHelp] = useState<
        IntegrationType | undefined
    >();

    const {
        register,
        submitting,
        control,
        watch,
        setValue,
        formState: {errors},
    } = usedFormSubmit;

    const integration = watch('integration');

    useEffect(() => {
        setIntegrationHelp(undefined);
        if (integration) {
            (async () => {
                const r = await getIntegrationType(integration);
                setIntegrationHelp(r);
            })();
        }
    }, [integration]);

    const definitions = useMemo(() => {
        return [
            {
                type: PermissionType.Mask,
                key: AclPermission.VIEW,
                label: t('acl.permission.integration.view.label', 'View'),
                description: t(
                    'acl.permission.integration.view.desc',
                    'Can view integration and read its data'
                ),
            },
            {
                type: PermissionType.Mask,
                key: AclPermission.CHILD_EDIT,
                label: t(
                    'acl.permission.integration.child_edit.label',
                    'Interact'
                ),
                description: t(
                    'acl.permission.integration.child_edit.desc',
                    'Can interact with integration, such as running it or using it in a workflow, but cannot edit its configuration or delete it.'
                ),
            },
        ];
    }, [t]);

    const copyReference = () => {
        setValue('configYaml', integrationHelp!.reference);
    };

    return (
        <>
            <LastErrorsList data={data} />
            {!data.id && (
                <FormRow>
                    <IntegrationTypeSelect
                        control={control}
                        name={'integration'}
                        label={t(
                            'form.integration.integration.label',
                            'Integration'
                        )}
                        disabled={submitting}
                    />
                    <FormFieldErrors field={'integration'} errors={errors} />
                </FormRow>
            )}

            <FormRow>
                <TextField
                    label={t('form.integration.title.label', 'Title')}
                    {...register('title')}
                    disabled={submitting}
                />
                <FormFieldErrors field={'title'} errors={errors} />
            </FormRow>

            {data.configInfo && data.configInfo.length > 0 && (
                <Alert severity={'info'}>
                    <Typography variant={'body1'}>
                        {t(
                            'form.integration.config_info.title',
                            'Integration Keys'
                        )}
                    </Typography>
                    <MenuList>
                        {data.configInfo.map(configKey => (
                            <InfoRow
                                key={configKey.label}
                                label={configKey.label}
                                value={configKey.value}
                                copyValue={configKey.value}
                                description={configKey.description}
                            />
                        ))}
                    </MenuList>
                </Alert>
            )}

            <FormRow>
                <CheckboxWidget
                    label={t('form.integration.enabled.label', 'Enabled')}
                    control={control}
                    name={'enabled'}
                    disabled={submitting}
                />
            </FormRow>

            <FormRow>
                <TextField
                    label={t('form.integration.if.label', 'IF')}
                    {...register('if')}
                    placeholder={t(
                        'form.integration.if.placeholder',
                        'Condition to run the integration'
                    )}
                    disabled={submitting}
                />
                <FormFieldErrors field={'if'} errors={errors} />

                <FormHelperText>
                    {t(
                        'form.integration.if.helper',
                        'This condition is used to determine if the integration should be executed.'
                    )}
                    <br />
                    {t(
                        'form.integration.if.helper_examples',
                        'Examples of conditions:'
                    )}
                    <pre>{`asset.getSource().getType() matches '#^image/#'`}</pre>
                    <pre>{`asset.getCreatedAt() > date('2000-01-01')`}</pre>
                </FormHelperText>
            </FormRow>

            <FormRow>
                <FormGroup>
                    <FormLabel>
                        {t('form.integration.needs.label', 'Needs')}
                    </FormLabel>
                    <WorkspaceIntegrationSelect<WorkspaceIntegration, true>
                        disabled={submitting}
                        name={'needs'}
                        isMulti={true}
                        control={control}
                        workspaceId={workspace.id}
                        disabledValues={[`/integrations/${data.id}`]}
                        placeholder={t(
                            'form.integration.needs.placeholder',
                            'Select dependencies'
                        )}
                    />
                    <FormHelperText>
                        {t(
                            'form.integration.needs.helper',
                            'These integrations are required to be completed before this integration is run.'
                        )}
                    </FormHelperText>
                    <FormFieldErrors field={'needs'} errors={errors} />
                </FormGroup>
            </FormRow>

            <FormRow>
                <CodeEditorWidget
                    control={control}
                    name={`configYaml`}
                    disabled={submitting}
                    mode={'yaml'}
                    height={'200px'}
                />
                {integrationHelp ? (
                    <FormHelperText>
                        <Typography variant={'body1'}>
                            {t(
                                'form.integration.help.config_reference',
                                'Configuration reference:'
                            )}
                        </Typography>
                        <CodeEditor
                            mode={'yaml'}
                            theme={'github'}
                            height={'200px'}
                            value={integrationHelp.reference}
                            readOnly={true}
                        />
                        <Button
                            startIcon={<ContentCopyIcon />}
                            onClick={copyReference}
                        >
                            {t(
                                'form.integration.help.copy_reference',
                                'Copy reference'
                            )}
                        </Button>
                    </FormHelperText>
                ) : null}
                <FormFieldErrors field={'config'} errors={errors} />
            </FormRow>

            <FormRow>
                <CheckboxWidget
                    label={t('form.integration.public.label', 'Public')}
                    control={control}
                    name={'public'}
                    disabled={submitting}
                />
                <FormFieldErrors field={'public'} errors={errors} />
            </FormRow>

            {data.id && (
                <FormRow>
                    <InputLabel>
                        {t('form.permissions.label', 'Permissions')}
                    </InputLabel>
                    <AclForm
                        objectId={data.id}
                        objectType={PermissionObject.WorkspaceIntegration}
                        filterDefinitions={d =>
                            d.type === PermissionType.Mask &&
                            [
                                AclPermission.VIEW,
                                AclPermission.CHILD_EDIT,
                            ].includes(d.key)
                        }
                        definitions={definitions}
                    />
                </FormRow>
            )}
        </>
    );
}

function ListItem({data}: DefinitionItemProps<WorkspaceIntegration>) {
    return (
        <>
            <ListItemText
                primary={data.title || data.integrationTitle}
                primaryTypographyProps={{
                    color: data.enabled ? undefined : 'error',
                }}
                secondary={data.title ? data.integrationTitle : undefined}
            />
        </>
    );
}

function createNewItem(): Partial<WorkspaceIntegration> {
    return {
        title: '',
        config: {},
        public: true,
        enabled: true,
    };
}

type Props = DataTabProps<Workspace>;

export default function IntegrationManager({
    data: workspace,
    minHeight,
    onClose,
}: Props) {
    const {t} = useTranslation();

    const handleSave = async (data: WorkspaceIntegration) => {
        if (data.id) {
            return await putIntegration(data.id, data);
        } else {
            return await postIntegration({
                ...data,
                workspace: `/workspaces/${workspace.id}`,
            });
        }
    };

    return (
        <DefinitionManager
            searchFilter={({items}, value) =>
                search<WorkspaceIntegration>(
                    items,
                    ['title', 'integrationTitle'],
                    value
                )
            }
            itemComponent={Item}
            listComponent={ListItem}
            load={() => getWorkspaceIntegrations(workspace.id)}
            workspace={workspace}
            minHeight={minHeight}
            onClose={onClose}
            createNewItem={createNewItem}
            newLabel={t('integrations.new.label', 'New Integration')}
            handleSave={handleSave}
            handleDelete={deleteIntegration}
        />
    );
}
