'use client';

import {useId, useState} from 'react';
import Link from 'next/link';
import {useQuery} from '@tanstack/react-query';
import {useTranslation} from 'react-i18next';
import {ArrowLeftIcon, CheckIcon, LinkIcon} from 'lucide-react';
import {Button} from '@alchemy/next-ui/ui/button';
import {Alert} from '@alchemy/next-ui/ui/misc';
import {Input, FormRow} from '@alchemy/next-ui/ui/input';
import {FullPageLoader} from '@alchemy/next-ui/ui/loader';
import {Page} from '@/components/layout/AppShell';
import {downloadUrl, getTarget, hasFormErrors} from '@/lib/api/uploader';
import {routes} from '@/lib/routes';
import {getErrorMessage} from '@alchemy/next-ui/utils/errors';
import {useDirtyState} from '@alchemy/next-ui/navigation/unsavedChanges';
import {useTargetFormSchema} from '@/features/upload/UploadScreen';
import {
    UploadFormBody,
    useTargetOptionsLoader,
    useUploadForm,
} from '@/features/upload/UploadForm';
import type {FormSchema} from '@/types/api';

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
            title={t('download.title', 'Import from a URL')}
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
                    'The file is fetched from its URL, then sent to {{target}}.',
                    {target: target.data.name}
                )}
            </p>
            {done ? (
                <div
                    className="flex flex-col gap-4"
                    data-testid="download-done"
                >
                    <Alert variant="success" icon={<CheckIcon />}>
                        {t(
                            'download.done',
                            'Import started: the file will be added to {{target}} once fetched. You can leave this page.',
                            {target: target.data.name}
                        )}
                    </Alert>
                    <div>
                        <Button
                            data-testid="download-another"
                            onClick={() => {
                                setRound(r => r + 1);
                                setDone(false);
                            }}
                        >
                            <LinkIcon />{' '}
                            {t('download.another', 'Import another file')}
                        </Button>
                    </div>
                </div>
            ) : (
                <DownloadForm
                    key={round}
                    targetId={targetId}
                    schema={schema.data}
                    onDone={() => setDone(true)}
                />
            )}
        </Page>
    );
}

function DownloadForm({
    targetId,
    schema,
    onDone,
}: {
    targetId: string;
    schema: FormSchema | null | undefined;
    onDone: () => void;
}) {
    const {t} = useTranslation();
    const urlId = useId();
    const form = useUploadForm(schema);
    const loadOptions = useTargetOptionsLoader(targetId);
    const schemaId = schema?.id;
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
                form.setServerErrors(errors);
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
                    <LinkIcon /> {t('download.submit', 'Import')}
                </Button>
            </div>
        </form>
    );
}
