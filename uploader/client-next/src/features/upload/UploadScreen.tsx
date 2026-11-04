'use client';

import {useCallback, useEffect, useRef, useState} from 'react';
import {useQuery} from '@tanstack/react-query';
import {useTranslation} from 'react-i18next';
import {CheckIcon, HomeIcon, PencilIcon, RotateCwIcon} from 'lucide-react';
import {Button} from '@alchemy/next-ui/ui/button';
import {Alert, Progress} from '@alchemy/next-ui/ui/misc';
import {FullPageLoader} from '@alchemy/next-ui/ui/loader';
import {useConfirm} from '@alchemy/next-ui/ui/confirm';
import {Page} from '@/components/layout/AppShell';
import {useAuth} from '@/lib/auth/AuthProvider';
import {commit, getTarget, getTargetFormSchema} from '@/lib/api/uploader';
import {useUnsavedChangesPrompt} from '@alchemy/next-ui/navigation/unsavedChanges';
import {getErrorMessage} from '@alchemy/next-ui/utils/errors';
import {formatFileSize} from '@/lib/utils/format';
import {cn} from '@alchemy/next-ui/utils/cn';
import type {LiFormValues} from '@/features/liform/types';
import {FileSelectionStep} from './FileSelectionStep';
import {MetadataFormStep} from './MetadataFormStep';
import {FileTile} from './FileTile';
import {useUploadErrorMessage, useUploadQueue} from './useUploadQueue';

export type WizardStep = 'select' | 'form' | 'progress' | 'done';

type CommitState =
    {status: 'idle'} | {status: 'pending'} | {status: 'error'; message: string};

type Sent = {name: string; size: number}[];

export function useTargetFormSchema(targetId: string) {
    const {i18n} = useTranslation();

    // The API picks the schema matching the language of the request
    return useQuery({
        queryKey: ['targets', targetId, 'form-schema', i18n.language],
        queryFn: () => getTargetFormSchema(targetId),
    });
}

export function UploadScreen({targetId}: {targetId: string}) {
    const {t} = useTranslation();
    const {user} = useAuth();
    const confirm = useConfirm();
    const target = useQuery({
        queryKey: ['targets', targetId],
        queryFn: () => getTarget(targetId),
    });
    const schema = useTargetFormSchema(targetId);
    const {queue, snapshot} = useUploadQueue(targetId, user!.username);
    const errorMessage = useUploadErrorMessage();

    const [step, setStep] = useState<WizardStep>('select');
    const [files, setFiles] = useState<File[]>([]);
    // Set once the form is validated (empty without schema)
    const [formData, setFormData] = useState<LiFormValues>();
    const [commitState, setCommitState] = useState<CommitState>({
        status: 'idle',
    });
    const [sent, setSent] = useState<Sent>([]);
    const committing = useRef(false);

    useUnsavedChangesPrompt(files.length > 0 && step !== 'done');

    const reset = useCallback(() => {
        queue.cancel();
        setFiles([]);
        setFormData(undefined);
        setCommitState({status: 'idle'});
        setStep('select');
    }, [queue]);

    const cancel = async () => {
        if (
            await confirm({
                title: t('upload.cancel_title', 'Cancel the upload'),
                description: t(
                    'upload.cancel_confirm',
                    'Are you sure you want to cancel current upload?'
                ),
                confirmLabel: t('upload.cancel_yes', 'Cancel the upload'),
                cancelLabel: t('upload.cancel_no', 'Continue'),
                destructive: true,
            })
        ) {
            reset();
        }
    };

    const startUpload = () => {
        queue.add(files);
        if (schema.data) {
            setStep('form');
        } else {
            setFormData({});
            setStep('progress');
        }
    };

    const schemaId = schema.data?.id;
    const runCommit = useCallback(async () => {
        if (committing.current || !formData) {
            return;
        }
        committing.current = true;
        setCommitState({status: 'pending'});
        const items = queue.getSnapshot().items;
        try {
            await commit({
                targetId,
                files: items.map(i => i.assetId!),
                formData,
                schemaId,
            });
            setSent(items.map(i => ({name: i.file.name, size: i.file.size})));
            setCommitState({status: 'idle'});
            setStep('done');
            setFiles([]);
            setFormData(undefined);
        } catch (e) {
            setCommitState({status: 'error', message: getErrorMessage(e)});
        } finally {
            committing.current = false;
        }
    }, [formData, queue, schemaId, targetId]);

    // Committed as soon as both the files and the form are there
    useEffect(() => {
        if (
            step === 'progress' &&
            snapshot.complete &&
            formData &&
            commitState.status === 'idle'
        ) {
            void runCommit();
        }
    }, [step, snapshot.complete, formData, commitState.status, runCommit]);

    // Every file was removed from the batch
    useEffect(() => {
        if (
            (step === 'form' || step === 'progress') &&
            snapshot.items.length === 0 &&
            commitState.status !== 'pending'
        ) {
            reset();
        }
    }, [step, snapshot.items.length, commitState.status, reset]);

    if (target.isPending) {
        return <FullPageLoader />;
    }
    if (target.error) {
        return (
            <Page>
                <Alert variant="destructive">
                    {getErrorMessage(target.error)}
                </Alert>
            </Page>
        );
    }

    const percent =
        snapshot.total > 0
            ? Math.round((snapshot.loaded / snapshot.total) * 100)
            : 0;

    return (
        <Page title={target.data.name}>
            <Stepper step={step} withForm={!!schema.data} />

            {step === 'select' ? (
                <>
                    {schema.error ? (
                        <Alert variant="destructive" className="mb-4">
                            {getErrorMessage(schema.error)}
                        </Alert>
                    ) : null}
                    <FileSelectionStep
                        targetId={targetId}
                        files={files}
                        onChange={setFiles}
                        onNext={startUpload}
                        nextDisabled={schema.isPending || !!schema.error}
                    />
                </>
            ) : null}

            {step === 'form' && schema.data ? (
                <div className="flex flex-col gap-6">
                    <BatchProgress
                        percent={percent}
                        loaded={snapshot.loaded}
                        total={snapshot.total}
                    />
                    <MetadataFormStep
                        targetId={targetId}
                        schema={schema.data.data}
                        initialValues={formData}
                        onCancel={cancel}
                        onSubmit={values => {
                            setFormData(values);
                            setStep('progress');
                        }}
                    />
                </div>
            ) : null}

            {step === 'progress' ? (
                <div
                    className="flex flex-col gap-4"
                    data-testid="upload-progress"
                >
                    <BatchProgress
                        percent={percent}
                        loaded={snapshot.loaded}
                        total={snapshot.total}
                    />
                    {snapshot.failed > 0 ? (
                        <Alert variant="warning" data-testid="batch-failed">
                            {t('upload.some_failed', {
                                defaultValue_one:
                                    '{{count}} file could not be sent: retry it, or remove it to send the others.',
                                defaultValue_other:
                                    '{{count}} files could not be sent: retry them, or remove them to send the others.',
                                count: snapshot.failed,
                            })}
                        </Alert>
                    ) : null}
                    <ul className="flex flex-wrap gap-3">
                        {snapshot.items.map(item => (
                            <FileTile
                                key={item.id}
                                file={item.file}
                                status={item.status}
                                loaded={item.loaded}
                                error={errorMessage(item)}
                                onRetry={() => queue.retry(item.id)}
                                onRemove={() => queue.remove(item.id)}
                            />
                        ))}
                    </ul>
                    {commitState.status === 'pending' ? (
                        <Alert variant="info">
                            {t('upload.committing', 'Submitting the batch…')}
                        </Alert>
                    ) : null}
                    {commitState.status === 'error' ? (
                        <Alert
                            variant="destructive"
                            data-testid="commit-error"
                            title={t(
                                'upload.commit_failed',
                                'The batch could not be submitted'
                            )}
                        >
                            <div className="flex flex-col gap-3">
                                <span className="whitespace-pre-line">
                                    {commitState.message}
                                </span>
                                <div className="flex flex-wrap gap-2">
                                    <Button
                                        size="sm"
                                        data-testid="commit-retry"
                                        onClick={() => {
                                            setCommitState({status: 'idle'});
                                        }}
                                    >
                                        <RotateCwIcon />{' '}
                                        {t('upload.commit_retry', 'Try again')}
                                    </Button>
                                    {schema.data ? (
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            onClick={() => {
                                                setCommitState({
                                                    status: 'idle',
                                                });
                                                setStep('form');
                                            }}
                                        >
                                            <PencilIcon />{' '}
                                            {t(
                                                'upload.edit_form',
                                                'Edit the form'
                                            )}
                                        </Button>
                                    ) : null}
                                </div>
                            </div>
                        </Alert>
                    ) : null}
                    <div>
                        <Button
                            variant="outline"
                            data-testid="upload-cancel"
                            disabled={commitState.status === 'pending'}
                            onClick={cancel}
                        >
                            {t('upload.cancel', 'Cancel')}
                        </Button>
                    </div>
                </div>
            ) : null}

            {step === 'done' ? (
                <div className="flex flex-col gap-4" data-testid="upload-done">
                    <Alert
                        variant="success"
                        icon={<CheckIcon />}
                        title={t('upload.done_title', "You're done!")}
                    >
                        {t('upload.done', 'All files have been uploaded.')}
                    </Alert>
                    <ul
                        data-testid="sent-files"
                        className="divide-y rounded-md border text-sm"
                    >
                        {sent.map((f, i) => (
                            <li
                                key={i}
                                className="flex items-center justify-between gap-3 px-3 py-2"
                            >
                                <span className="truncate">{f.name}</span>
                                <span className="shrink-0 text-muted-foreground">
                                    {formatFileSize(f.size)}
                                </span>
                            </li>
                        ))}
                    </ul>
                    <div>
                        <Button data-testid="upload-again" onClick={reset}>
                            <HomeIcon /> {t('upload.back_home', 'Go back Home')}
                        </Button>
                    </div>
                </div>
            ) : null}
        </Page>
    );
}

function BatchProgress({
    percent,
    loaded,
    total,
}: {
    percent: number;
    loaded: number;
    total: number;
}) {
    const {t} = useTranslation();

    return (
        <div className="flex flex-col gap-1.5" data-testid="batch-progress">
            <div className="flex justify-between text-sm text-muted-foreground">
                <span>
                    {t('upload.progress', 'Uploading: {{percent}}%', {percent})}
                </span>
                <span>
                    {formatFileSize(loaded)} / {formatFileSize(total)}
                </span>
            </div>
            <Progress value={percent} aria-label={`${percent}%`} />
        </div>
    );
}

function Stepper({step, withForm}: {step: WizardStep; withForm: boolean}) {
    const {t} = useTranslation();
    const steps: {id: WizardStep; label: string}[] = [
        {id: 'select', label: t('upload.step.select', 'Files')},
        ...(withForm
            ? [
                  {
                      id: 'form' as const,
                      label: t('upload.step.form', 'Information'),
                  },
              ]
            : []),
        {id: 'progress', label: t('upload.step.progress', 'Upload')},
        {id: 'done', label: t('upload.step.done', 'Done')},
    ];
    const current = steps.findIndex(s => s.id === step);

    return (
        <ol
            className="mb-6 flex items-center gap-2 text-sm"
            data-testid="stepper"
        >
            {steps.map((s, i) => (
                <li
                    key={s.id}
                    aria-current={i === current ? 'step' : undefined}
                    className="flex items-center gap-2"
                >
                    {i > 0 ? (
                        <span aria-hidden className="h-px w-6 bg-border" />
                    ) : null}
                    <span
                        className={cn(
                            'flex size-6 items-center justify-center rounded-full border text-xs',
                            i < current &&
                                'border-primary bg-primary text-primary-foreground',
                            i === current && 'border-primary text-primary'
                        )}
                    >
                        {i < current ? (
                            <CheckIcon className="size-3.5" />
                        ) : (
                            i + 1
                        )}
                    </span>
                    <span
                        className={cn(
                            'hidden sm:inline',
                            i === current
                                ? 'font-medium'
                                : 'text-muted-foreground'
                        )}
                    >
                        {s.label}
                    </span>
                </li>
            ))}
        </ol>
    );
}
