import React from 'react';
import {FormData, Target, UploadedFile} from '../../types.ts';
import UploadBatch from '../../uploadBatch';
import {toast} from 'react-toastify';
import FilePicker from './FilePicker.tsx';
import '../../scss/Upload.scss';
import UploadForm from './UploadForm.tsx';
import UploadProgress from '../page/UploadProgress';
import {useTranslation} from 'react-i18next';
import UploadDone from '../page/UploadDone';
import {useInRouterDirtyFormPrompt} from '@alchemy/navigation';

enum Step {
    Files,
    Form,
    Progress,
    Done,
}

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

    const onSubmitForm = React.useCallback(
        (data: FormData) => {
            uploadBatch.formData = data;
            setStep(Step.Progress);
        },
        [uploadBatch, setStep]
    );

    const reset = React.useCallback(() => {
        uploadBatch.abort();
        setFiles([]);
        setStep(Step.Files);
    }, [uploadBatch, setStep, setFiles]);

    const onCancel = React.useCallback(() => {
        if (window.confirm(`Are you sure you want to cancel current upload?`)) {
            reset();
        }
    }, [reset]);

    useInRouterDirtyFormPrompt(t, files.length > 0);

    return (
        <>
            <h2
                style={{
                    textAlign: 'center',
                    fontSize: 20,
                    marginBottom: 20,
                }}
            >
                {target.name}
            </h2>

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
