import {useFormSubmit} from '@alchemy/api';
import {FormSchema, LiFormSchema} from '../../types.ts';
import {toast} from 'react-toastify';
import {Trans, useTranslation} from 'react-i18next';
import {Box, Button, Paper} from '@mui/material';
import AssetLiForm from '../Upload/AssetLiForm.tsx';
import React from 'react';
import {FormRow, RemoteErrors} from '@alchemy/react-form';
import {Controller} from 'react-hook-form';
import AceEditor from 'react-ace';
import 'ace-builds/src-noconflict/theme-github';
import 'ace-builds/src-noconflict/ext-language_tools';
import 'ace-builds/src-noconflict/mode-json';
import {useModals} from '@alchemy/navigation';
import {AlertDialog} from '@alchemy/phrasea-framework';
import {putFormSchema} from '../../api/formSchemaApi.ts';

type Props = {
    formSchema: FormSchema;
};

export default function FormSchemaForm({formSchema}: Props) {
    const {t} = useTranslation();
    const [lastValidSchema, setLastValidSchema] = React.useState<
        LiFormSchema | undefined
    >();
    const [error, setError] = React.useState<string>();
    const {openModal} = useModals();

    const {handleSubmit, watch, control} = useFormSubmit({
        defaultValues: {
            data: JSON.stringify(formSchema.data, null, 2),
        },
        onSubmit: async data => {
            return await putFormSchema(formSchema.id, {
                data: JSON.parse(data.data) as LiFormSchema,
            });
        },
        onSuccess: async _data => {
            toast.success(
                t('form_editor.form_saved', 'Form saved successfully')
            );
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
                        <FormRow>
                            <Button type={'submit'} variant={'contained'}>
                                {t('form_editor.save_form', 'Save Form')}
                            </Button>
                        </FormRow>
                        {error ? <RemoteErrors errors={[error]} /> : null}
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
