import React from 'react';
import {Target, UploadedFile, UploadFormData} from '../../types.ts';
import UploadBatch from '../../uploadBatch.ts';
import {toast} from 'react-toastify';
import FilePicker from './FilePicker.tsx';
import UploadForm from './UploadForm.tsx';
import UploadDone from './UploadDone.tsx';
import {useFormPrompt} from '@alchemy/navigation';
import {Container, Typography} from '@mui/material';
import UploadProgress from './UploadProgress.tsx';
import {useTranslation} from 'react-i18next';
import {MenuClasses} from '@alchemy/phrasea-framework';

enum Step {
    Files,
    Form,
    Progress,
    Done,
}

export type OnSubmitForm = (data: UploadFormData, schemaId?: string) => void;

type Props = {
    target: Target;
};

export default function UploadStepper({target}: Props) {
    const {t} = useTranslation();
    const [step, setStep] = React.useState(Step.Files);
    const [files, setFiles] = React.useState<UploadedFile[]>([]);
    const uploadBatch = React.useMemo(
        () => new UploadBatch(target.id),
        [target]
    );

    const onError = React.useCallback((err: string) => {
        toast.error(err);
    }, []);

    React.useEffect(() => {
        uploadBatch.addErrorListener(onError);

        return () => {
            uploadBatch.removeErrorListener(onError);
        };
    }, [onError, uploadBatch]);

    const onSubmitFiles = React.useCallback(() => {
        uploadBatch.addFiles(files);
        uploadBatch.startUpload();
        setStep(Step.Form);
    }, [uploadBatch, setStep, files]);

    const onSubmitForm = React.useCallback<OnSubmitForm>(
        (data: UploadFormData, schemaId?: string) => {
            uploadBatch.formData = data;
            uploadBatch.schemaId = schemaId;
            setStep(Step.Progress);
        },
        [uploadBatch, setStep]
    );

    const reset = React.useCallback(() => {
        uploadBatch.reset();
        setFiles([]);
        setStep(Step.Files);
    }, [uploadBatch, setStep, setFiles]);

    const onCancel = React.useCallback(() => {
        if (
            window.confirm(
                t(
                    'upload.cancelConfirmation',
                    `Are you sure you want to cancel current upload?`
                )
            )
        ) {
            reset();
        }
    }, [reset]);

    useFormPrompt(t, files.length > 0);

    return (
        <>
            <div className={MenuClasses.PageHeader}>
                <Container>
                    <Typography
                        variant={'h2'}
                        sx={{
                            textAlign: 'center',
                            fontSize: 20,
                            mt: 3,
                            mb: 3,
                        }}
                    >
                        {target.name}
                    </Typography>
                </Container>
            </div>

            {step === Step.Files && (
                <FilePicker
                    target={target}
                    onSubmit={onSubmitFiles}
                    files={files}
                    setFiles={setFiles}
                />
            )}

            {step === Step.Form && (
                <UploadForm
                    target={target}
                    onSubmit={onSubmitForm}
                    onCancel={onCancel}
                    files={files}
                />
            )}

            {step === Step.Progress && (
                <UploadProgress
                    uploadBatch={uploadBatch}
                    onCancel={onCancel}
                    onNext={() => {
                        setStep(Step.Done);
                    }}
                    files={files}
                />
            )}

            {step === Step.Done && <UploadDone onRestart={reset} />}
        </>
    );
}
