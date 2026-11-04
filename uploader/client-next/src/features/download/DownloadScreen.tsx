'use client';

import {useId, useState} from 'react';
import Link from 'next/link';
import {useQuery} from '@tanstack/react-query';
import {useTranslation} from 'react-i18next';
import {ArrowLeftIcon, CheckIcon, DownloadIcon} from 'lucide-react';
import {Button} from '@/components/ui/button';
import {Alert} from '@/components/ui/misc';
import {Input, FormRow} from '@/components/ui/input';
import {FullPageLoader} from '@/components/ui/loader';
import {Page} from '@/components/layout/AppShell';
import {downloadUrl, getTarget, hasFormErrors} from '@/lib/api/uploader';
import {routes} from '@/lib/routes';
import {getErrorMessage} from '@/lib/utils/errors';
import {useDirtyState} from '@/lib/navigation/unsavedChanges';
import {LiFormBody} from '@/features/liform/LiFormBody';
import {fromServerErrors, useLiForm} from '@/features/liform/useLiForm';
import {useTargetFormSchema} from '@/features/upload/UploadScreen';
import type {LiFormSchema} from '@/features/liform/types';

export function isHttpUrl(value: string): boolean {
    try {
        const url = new URL(value);

        return url.protocol === 'http:' || url.protocol === 'https:';
    } catch {
        return false;
    }
}

/**
 * Upload by URL: the server downloads the file in the background, then
 * commits it to the target like a batch of one file.
 */
export function DownloadScreen({targetId}: {targetId: string}) {
    const {t} = useTranslation();
    const target = useQuery({
        queryKey: ['targets', targetId],
        queryFn: () => getTarget(targetId),
    });
    const schema = useTargetFormSchema(targetId);
    const [done, setDone] = useState(false);
    // A new form for every download
    const [round, setRound] = useState(0);

    if (target.isPending || schema.isPending) {
        return <FullPageLoader />;
    }
    if (target.error || schema.error) {
        return (
            <Page>
                <Alert variant="destructive">
                    {getErrorMessage(target.error ?? schema.error)}
                </Alert>
            </Page>
        );
    }

    return (
        <Page
            title={t('download.title', 'Download Asset')}
            actions={
                <Button variant="outline" asChild>
                    <Link href={routes.home()} data-testid="download-back">
                        <ArrowLeftIcon /> {t('download.back', 'Back')}
                    </Link>
                </Button>
            }
        >
            <p className="mb-6 text-muted-foreground">
                {t(
                    'download.subtitle',
                    'Fill the form to download your asset into {{target}}.',
                    {target: target.data.name}
                )}
            </p>
            {done ? (
                <div
                    className="flex flex-col gap-4"
                    data-testid="download-done"
                >
                    <Alert variant="success" icon={<CheckIcon />}>
                        {t('download.done', 'Your file will be downloaded!')}
                    </Alert>
                    <div>
                        <Button
                            data-testid="download-another"
                            onClick={() => {
                                setRound(r => r + 1);
                                setDone(false);
                            }}
                        >
                            <DownloadIcon />{' '}
                            {t('download.another', 'Download Another')}
                        </Button>
                    </div>
                </div>
            ) : (
                <DownloadForm
                    key={round}
                    targetId={targetId}
                    schema={schema.data?.data}
                    schemaId={schema.data?.id}
                    onDone={() => setDone(true)}
                />
            )}
        </Page>
    );
}

function DownloadForm({
    targetId,
    schema,
    schemaId,
    onDone,
}: {
    targetId: string;
    schema: LiFormSchema | undefined;
    schemaId: string | undefined;
    onDone: () => void;
}) {
    const {t} = useTranslation();
    const urlId = useId();
    const form = useLiForm(schema);
    const [url, setUrl] = useState('');
    const [urlError, setUrlError] = useState<string>();
    const [submitting, setSubmitting] = useState(false);
    const [error, setError] = useState<string>();
    useDirtyState({url, values: form.values});

    const submit = async () => {
        const values = form.validate();
        const urlInvalid = !url.trim()
            ? t('liform.error.required', 'This value is required.')
            : !isHttpUrl(url.trim())
              ? t('download.invalid_url', 'This is not a valid URL.')
              : undefined;
        setUrlError(urlInvalid);
        if (!values || urlInvalid) {
            return;
        }
        setSubmitting(true);
        setError(undefined);
        try {
            const errors = await downloadUrl({
                targetId,
                url: url.trim(),
                formData: values,
                schemaId,
            });
            if (hasFormErrors(errors)) {
                form.setErrors(fromServerErrors(errors));
                setSubmitting(false);

                return;
            }
            onDone();
        } catch (e) {
            setError(getErrorMessage(e));
            setSubmitting(false);
        }
    };

    return (
        <form
            data-testid="download-form"
            noValidate
            onSubmit={e => {
                e.preventDefault();
                void submit();
            }}
        >
            <FormRow
                label={
                    <>
                        {t('download.url', 'File URL')}
                        <span aria-hidden className="text-destructive">
                            *
                        </span>
                    </>
                }
                htmlFor={urlId}
                error={urlError}
            >
                <Input
                    id={urlId}
                    type="url"
                    name="url"
                    required
                    autoFocus
                    placeholder="https://"
                    disabled={submitting}
                    aria-invalid={urlError ? true : undefined}
                    value={url}
                    onChange={e => setUrl(e.target.value)}
                />
            </FormRow>
            <LiFormBody form={form} disabled={submitting} />
            {error ? (
                <Alert variant="destructive" className="mb-4">
                    {error}
                </Alert>
            ) : null}
            <div className="flex justify-between gap-3">
                <Button variant="outline" asChild>
                    <Link href={routes.home()}>
                        {t('download.cancel', 'Cancel')}
                    </Link>
                </Button>
                <Button
                    type="submit"
                    loading={submitting}
                    data-testid="download-submit"
                >
                    <DownloadIcon /> {t('download.submit', 'Download')}
                </Button>
            </div>
        </form>
    );
}
