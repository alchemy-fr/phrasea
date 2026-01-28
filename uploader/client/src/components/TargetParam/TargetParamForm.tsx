import {useFormSubmit} from '@alchemy/api';
import {TargetParam, TargetParamData} from '../../types.ts';
import {toast} from 'react-toastify';
import {useTranslation} from 'react-i18next';
import {Button, Paper} from '@mui/material';
import React from 'react';
import {FormFieldErrors, FormRow, RemoteErrors} from '@alchemy/react-form';
import {Controller} from 'react-hook-form';
import AceEditor from 'react-ace';
import 'ace-builds/src-noconflict/theme-github';
import 'ace-builds/src-noconflict/ext-language_tools';
import 'ace-builds/src-noconflict/mode-json';
import {getPath, useNavigate} from '@alchemy/navigation';
import {postTargetParam, putTargetParam} from '../../api/targetParamApi.ts';
import {routes} from '../../routes.ts';
import {targetEntity} from '../../api/targetApi.ts';
import TargetSelectWidget from '../TargetSelectWidget.tsx';

type Props = {
    data: Partial<TargetParam>;
};

export default function TargetParamForm({data}: Props) {
    const {t} = useTranslation();
    const navigate = useNavigate();
    const idRef = React.useRef(data.id);

    const {
        handleSubmit,
        control,
        remoteErrors,
        formState: {errors},
    } = useFormSubmit({
        defaultValues: {
            ...data,
            target: data.target
                ? `/${targetEntity}/${data.target.id}`
                : undefined,
            data: JSON.stringify(data.data, null, 2),
        },
        onSubmit: async newData => {
            const d = {
                ...newData,
                data: JSON.parse(newData.data) as TargetParamData,
                id: undefined,
            };

            if (data.id) {
                return await putTargetParam(data.id, d);
            } else {
                return await postTargetParam(d);
            }
        },
        onSuccess: async data => {
            toast.success(
                t('target_param.form_saved', 'Form saved successfully')
            );

            if (!idRef.current) {
                idRef.current = data.id;
                navigate(
                    getPath(routes.admin.routes.targetParam.routes.edit, {
                        id: data.id,
                    })
                );
            }
        },
    });

    return (
        <Paper sx={{p: 2, mb: 4}}>
            <form onSubmit={handleSubmit}>
                <FormRow>
                    <TargetSelectWidget
                        label={t('target_param.target.label', 'Target')}
                        control={control}
                        name={'target'}
                    />
                    <FormFieldErrors field={'target'} errors={errors} />
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
                                    height={'300px'}
                                />
                            );
                        }}
                    />
                </FormRow>
                <RemoteErrors errors={remoteErrors} />
                <FormRow>
                    <Button type={'submit'} variant={'contained'}>
                        {t('target_param.save_form', 'Save Form')}
                    </Button>
                </FormRow>
            </form>
        </Paper>
    );
}
