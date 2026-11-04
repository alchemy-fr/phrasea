'use client';

import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {ArrowRightIcon} from 'lucide-react';
import {Button} from '@/components/ui/button';
import {Alert} from '@/components/ui/misc';
import {getErrorMessage} from '@/lib/utils/errors';
import {hasFormErrors, validateForm} from '@/lib/api/uploader';
import {LiFormBody} from '@/features/liform/LiFormBody';
import {fromServerErrors, useLiForm} from '@/features/liform/useLiForm';
import type {LiFormSchema, LiFormValues} from '@/features/liform/types';

type Props = {
    targetId: string;
    schema: LiFormSchema;
    /** Values entered earlier (back from a refused commit) */
    initialValues?: LiFormValues;
    onCancel: () => void;
    onSubmit: (values: LiFormValues) => void;
};

/**
 * The metadata of the batch, filled while the files are being sent. The API
 * validates them before the next step: a batch is never committed with data
 * it would refuse.
 */
export function MetadataFormStep({
    targetId,
    schema,
    initialValues,
    onCancel,
    onSubmit,
}: Props) {
    const {t} = useTranslation();
    const form = useLiForm(schema, initialValues);
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
                form.setErrors(fromServerErrors(errors));

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
            <LiFormBody form={form} disabled={submitting} />
            {error ? (
                <Alert variant="destructive" className="mb-4">
                    {error}
                </Alert>
            ) : null}
            <div className="flex justify-between gap-3">
                <Button
                    type="button"
                    variant="outline"
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
                    {t('upload.next', 'Next')} <ArrowRightIcon />
                </Button>
            </div>
        </form>
    );
}
