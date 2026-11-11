'use client';

import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {SendIcon} from 'lucide-react';
import {Button} from '@alchemy/next-ui/ui/button';
import {Alert} from '@alchemy/next-ui/ui/misc';
import {getErrorMessage} from '@alchemy/next-ui/utils/errors';
import {hasFormErrors, validateForm} from '@/lib/api/uploader';
import type {FormDefinition, FormValues} from '@/features/form-builder/types';
import {
    UploadFormBody,
    useTargetOptionsLoader,
    useUploadForm,
} from './UploadForm';

type Props = {
    targetId: string;
    form: FormDefinition;
    /** Values entered earlier (back from a refused commit) */
    initialValues?: FormValues;
    onCancel: () => void;
    onSubmit: (values: FormValues) => void;
};

/**
 * The metadata of the batch, filled while the files are being sent. The API
 * validates them before the next step: a batch is never committed with data
 * it would refuse.
 */
export function MetadataFormStep({
    targetId,
    form: definition,
    initialValues,
    onCancel,
    onSubmit,
}: Props) {
    const {t} = useTranslation();
    const form = useUploadForm(definition, initialValues);
    const loadOptions = useTargetOptionsLoader(targetId);
    const [submitting, setSubmitting] = useState(false);
    const [error, setError] = useState<string>();

    const submit = async () => {
        const values = form.validate();
        if (!values) {
            return;
        }
        setSubmitting(true);
        setError(undefined);
        try {
            const errors = await validateForm(targetId, values);
            if (hasFormErrors(errors)) {
                form.setServerErrors(errors);

                return;
            }
            onSubmit(values);
        } catch (e) {
            setError(getErrorMessage(e));
        } finally {
            setSubmitting(false);
        }
    };

    return (
        <form
            data-testid="metadata-form"
            noValidate
            onSubmit={e => {
                e.preventDefault();
                void submit();
            }}
        >
            <UploadFormBody
                form={form}
                loadOptions={loadOptions}
                disabled={submitting}
            />
            {error ? (
                <Alert variant="destructive" className="mb-4">
                    {error}
                </Alert>
            ) : null}
            <div className="flex justify-between gap-3">
                <Button
                    type="button"
                    variant="ghost"
                    data-testid="upload-cancel"
                    onClick={onCancel}
                >
                    {t('upload.cancel', 'Cancel')}
                </Button>
                <Button
                    type="submit"
                    loading={submitting}
                    data-testid="metadata-submit"
                >
                    <SendIcon /> {t('upload.submit_form', 'Confirm and send')}
                </Button>
            </div>
        </form>
    );
}
