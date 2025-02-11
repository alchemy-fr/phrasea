import {IntegrationType, Workspace, WorkspaceIntegration} from '../../../types';
import {
    Alert, AlertTitle,
    Button,
    FormHelperText,
    ListItemText,
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
import {useEffect, useState} from 'react';
import IntegrationTypeSelect from '../../Form/IntegrationTypeSelect.tsx';
import CodeEditor from '../../Media/Asset/Widgets/CodeEditor.tsx';
import ContentCopyIcon from '@mui/icons-material/ContentCopy';
import LastErrors from "./LastErrors.tsx";

function Item({
    usedFormSubmit,
    data,
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

    const copyReference = () => {
        setValue('configYaml', integrationHelp!.reference);
    };

    return (
        <>
            <LastErrors data={data}/>
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

            <FormRow>
                <CheckboxWidget
                    label={t('form.integration.enabled.label', 'Enabled')}
                    control={control}
                    name={'enabled'}
                    disabled={submitting}
                />
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
                                'form.integration.help.config_refenrece',
                                'Configuration reference:'
                            )}
                        </Typography>
                        <pre>
                            <CodeEditor
                                mode={'yaml'}
                                theme={'github'}
                                height={'200px'}
                                value={integrationHelp.reference}
                                readOnly={true}
                            />
                        </pre>
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

type Props = {
    data: Workspace;
    onClose: () => void;
    minHeight?: number | undefined;
};

function createNewItem(): Partial<WorkspaceIntegration> {
    return {
        title: '',
        config: {},
        enabled: true,
    };
}

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
