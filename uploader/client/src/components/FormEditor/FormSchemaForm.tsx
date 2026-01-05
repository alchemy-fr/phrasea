import {useFormSubmit} from '@alchemy/api';
import {FormSchema, LiFormSchema} from '../../types.ts';
import {toast} from 'react-toastify';
import {Trans, useTranslation} from 'react-i18next';
import {Box, Button, Paper, TextField} from '@mui/material';
import AssetLiForm from '../Upload/AssetLiForm.tsx';
import React from 'react';
import {FormFieldErrors, FormRow, RemoteErrors} from '@alchemy/react-form';
import {Controller} from 'react-hook-form';
import AceEditor from 'react-ace';
import 'ace-builds/src-noconflict/theme-github';
import 'ace-builds/src-noconflict/ext-language_tools';
import 'ace-builds/src-noconflict/mode-json';
import {getPath, useModals, useNavigate} from '@alchemy/navigation';
import {AlertDialog} from '@alchemy/phrasea-framework';
import {postFormSchema, putFormSchema} from '../../api/formSchemaApi.ts';
import {routes} from '../../routes.ts';
import TargetSelectWidget from './TargetSelectWidget.tsx';

type Props = {
    formSchema: Partial<FormSchema>;
};

export default function FormSchemaForm({formSchema}: Props) {
    const {t} = useTranslation();
    const [lastValidSchema, setLastValidSchema] = React.useState<
        LiFormSchema | undefined
    >();
    const [error, setError] = React.useState<string>();
    const {openModal} = useModals();
    const navigate = useNavigate();
    const idRef = React.useRef(formSchema.id);

    const {
        handleSubmit,
        watch,
        control,
        register,
        remoteErrors,
        formState: {errors},
    } = useFormSubmit({
        defaultValues: {
            ...formSchema,
            target: formSchema.target ? `/targets/${formSchema.target.id}` : '',
            data: JSON.stringify(formSchema.data, null, 2),
        },
        onSubmit: async data => {
            const d = {
                ...data,
                data: JSON.parse(data.data) as LiFormSchema,
                id: undefined,
            };

            if (formSchema.id) {
                return await putFormSchema(formSchema.id, d);
            } else {
                return await postFormSchema(d);
            }
        },
        onSuccess: async data => {
            toast.success(
                t('form_editor.form_saved', 'Form saved successfully')
            );

            if (!idRef.current) {
                idRef.current = data.id;
                navigate(
                    getPath(routes.admin.routes.formSchema.routes.edit, {
                        id: data.id,
                    })
                );
            }
        },
    });

    const schemaData = watch('data');

    React.useEffect(() => {
        try {
            const parsed = JSON.parse(schemaData);
            setLastValidSchema(parsed);
            setError(undefined);
        } catch (e) {
            setError(
                t('form_editor.invalid_json', 'Invalid JSON: {{message}}', {
                    message: (e as Error).message,
                })
            );
        }
    }, [schemaData, setLastValidSchema, setError]);

    return (
        <Box
            sx={{
                'display': 'flex',
                'flexDirection': 'row',
                'gap': 2,
                'pb': 5,
                '> div': {
                    'width': '50%',
                    'p': 2,
                    'position': 'relative',
                    '> div': {
                        position: 'sticky',
                        top: 0,
                    },
                },
            }}
        >
            <Paper>
                <div>
                    <form onSubmit={handleSubmit}>
                        <FormRow>
                            <TargetSelectWidget
                                label={t('form_editor.target.label', 'Target')}
                                control={control}
                                name={'target'}
                            />
                            <FormFieldErrors field={'target'} errors={errors} />
                        </FormRow>
                        <FormRow>
                            <TextField
                                error={Boolean(errors.locale)}
                                {...register('locale')}
                                label={t('form_editor.locale.label', 'Locale')}
                                fullWidth
                                helperText={t(
                                    'form_editor.locale.helper',
                                    'Optional locale for this form schema (e.g., en, fr, etc.). Leave empty for all locales.'
                                )}
                            />
                            <FormFieldErrors field={'locale'} errors={errors} />
                        </FormRow>
                        <FormRow>
                            <Controller
                                control={control}
                                name={'data'}
                                render={({field: {onChange, value}}) => {
                                    return (
                                        <AceEditor
                                            theme="github"
                                            fontSize={15}
                                            mode={'json'}
                                            showPrintMargin={true}
                                            showGutter={true}
                                            highlightActiveLine={true}
                                            value={value}
                                            onChange={(value, event) => {
                                                onChange(value, event);
                                            }}
                                            editorProps={{
                                                $blockScrolling: true,
                                            }}
                                            setOptions={{
                                                enableBasicAutocompletion: false,
                                                enableLiveAutocompletion: true,
                                                enableSnippets: true,
                                                showLineNumbers: true,
                                                tabSize: 2,
                                                useWorker: false,
                                            }}
                                            width={'100%'}
                                            height={'700px'}
                                        />
                                    );
                                }}
                            />
                        </FormRow>
                        {error ? <RemoteErrors errors={[error]} /> : null}
                        <RemoteErrors errors={remoteErrors} />
                        <FormRow>
                            <Button type={'submit'} variant={'contained'}>
                                {t('form_editor.save_form', 'Save Form')}
                            </Button>
                        </FormRow>
                    </form>
                </div>
            </Paper>
            <Paper>
                <div>
                    {lastValidSchema ? (
                        <AssetLiForm
                            schema={lastValidSchema}
                            onSubmit={async data => {
                                openModal(AlertDialog, {
                                    title: t(
                                        'form_editor.form_submitted_title',
                                        'Form Submitted'
                                    ),
                                    children: (
                                        <Trans
                                            i18nKey="form_editor.form_submitted"
                                            values={{
                                                json: JSON.stringify(
                                                    data,
                                                    null,
                                                    2
                                                ),
                                            }}
                                            components={{pre: <pre />}}
                                            defaults={`Form submitted with data:<pre>{{json}}}</pre>`}
                                        />
                                    ),
                                });
                            }}
                        />
                    ) : null}
                </div>
            </Paper>
        </Box>
    );
}
