'use client';

import {
    ReactNode,
    useCallback,
    useEffect,
    useMemo,
    useRef,
    useState,
} from 'react';
import Link from 'next/link';
import {useQuery} from '@tanstack/react-query';
import {useTranslation} from 'react-i18next';
import {toast} from 'sonner';
import {
    CheckIcon,
    ChevronDownIcon,
    ChevronUpIcon,
    PencilIcon,
    PlusIcon,
    RotateCwIcon,
    UploadCloudIcon,
    WifiOffIcon,
    XIcon,
} from 'lucide-react';
import {Button} from '@alchemy/next-ui/ui/button';
import {Alert, Progress} from '@alchemy/next-ui/ui/misc';
import {FullPageLoader} from '@alchemy/next-ui/ui/loader';
import {useConfirm} from '@alchemy/next-ui/ui/confirm';
import {Page} from '@/components/layout/AppShell';
import {useAuth} from '@/lib/auth/AuthProvider';
import {useConfig} from '@/lib/config/ConfigProvider';
import {
    commit,
    deleteAsset,
    getAllTargets,
    getTarget,
    getTargetFormSchema,
} from '@/lib/api/uploader';
import {routes} from '@/lib/routes';
import {useUnsavedChangesPrompt} from '@alchemy/next-ui/navigation/unsavedChanges';
import {getErrorMessage} from '@alchemy/next-ui/utils/errors';
import {formatFileSize} from '@/lib/utils/format';
import {cn} from '@alchemy/next-ui/utils/cn';
import type {FormValues} from '@/features/form-builder/types';
import {targetsQueryKey} from '@/features/targets/TargetListScreen';
import type {
    QueueSnapshot,
    UploadItem,
    UploadQueue,
} from './engine/UploadQueue';
import {FileSelectionStep} from './FileSelectionStep';
import {MetadataFormStep} from './MetadataFormStep';
import {PreviewCacheProvider} from './previewCache';
import {FileTile} from './FileTile';
import {checkBatch} from './limits';
import {useBatchIssueMessage, useFileDrop} from './useFileDrop';
import {useUploadErrorMessage, useUploadQueue} from './useUploadQueue';

export type WizardStep = 'select' | 'form' | 'progress' | 'done';

type CommitState =
    {status: 'idle'} | {status: 'pending'} | {status: 'error'; message: string};

type Sent = {
    files: {name: string; size: number}[];
    /** The user asked to be notified once the target has processed them */
    notify: boolean;
};

/** Value of the built-in field asking for a notification */
const NOTIFY_FIELD = '__notify';

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
    const targets = useQuery({
        queryKey: targetsQueryKey,
        queryFn: getAllTargets,
    });
    const schema = useTargetFormSchema(targetId);
    const {queue, snapshot} = useUploadQueue(targetId, user!.username);
    const errorMessage = useUploadErrorMessage();

    const [step, setStep] = useState<WizardStep>('select');
    const [files, setFiles] = useState<File[]>([]);
    // Set once the form is validated (empty without schema)
    const [formData, setFormData] = useState<FormValues>();
    const [commitState, setCommitState] = useState<CommitState>({
        status: 'idle',
    });
    const [sent, setSent] = useState<Sent>({files: [], notify: false});
    const [showFiles, setShowFiles] = useState(false);
    const committing = useRef(false);
    // The assets belong to the commit once it is sent (it attaches them later)
    const committed = useRef(false);
    const uploadFiles = useMemo(
        () => [...files, ...snapshot.items.map(i => i.file)],
        [files, snapshot.items]
    );

    useUnsavedChangesPrompt(files.length > 0 && step !== 'done');

    /** Deletes the assets of files sent to a batch that will not be committed */
    const discard = useCallback((items: UploadItem[]) => {
        if (committing.current || committed.current) {
            return;
        }
        items.forEach(i => {
            if (i.assetId) {
                // Left to the expired assets cleanup if this fails
                deleteAsset(i.assetId).catch(() => undefined);
            }
        });
    }, []);

    // Read on unmount: the queue is already emptied by then
    const lastItems = useRef<UploadItem[]>([]);
    useEffect(() => {
        lastItems.current = snapshot.items;
    }, [snapshot.items]);
    useEffect(() => () => discard(lastItems.current), [discard]);

    const reset = useCallback(() => {
        queue.cancel();
        committed.current = false;
        setFiles([]);
        setFormData(undefined);
        setCommitState({status: 'idle'});
        setShowFiles(false);
        setStep('select');
    }, [queue]);

    const cancel = async () => {
        if (
            await confirm({
                title: t('upload.cancel_title', 'Cancel the upload'),
                description: t(
                    'upload.cancel_confirm',
                    'The files already sent and the information entered will be lost.'
                ),
                confirmLabel: t('upload.cancel_yes', 'Cancel the upload'),
                cancelLabel: t('upload.cancel_no', 'Continue the upload'),
                destructive: true,
            })
        ) {
            discard(queue.getSnapshot().items);
            reset();
        }
    };

    const removeItem = async (item: UploadItem) => {
        const confirmed = await confirm({
            title: t('upload.remove_file_title', 'Remove {{name}}', {
                name: item.file.name,
            }),
            description: item.assetId
                ? t(
                      'upload.remove_sent_confirm',
                      'The file was already sent: it will be deleted.'
                  )
                : t('upload.remove_file_confirm', 'The file will not be sent.'),
            confirmLabel: t('upload.remove_yes', 'Remove'),
            destructive: true,
        });
        if (!confirmed || committing.current || committed.current) {
            return;
        }
        // It may have finished sending while the dialog was open
        const current = queue.getSnapshot().items.find(i => i.id === item.id);
        if (!current) {
            return;
        }
        queue.remove(current.id);
        discard([current]);
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
            committed.current = true;
            setSent({
                files: items.map(i => ({name: i.file.name, size: i.file.size})),
                notify: formData[NOTIFY_FIELD] === true,
            });
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

    // 100% only once every file is created on the API, not when its bytes are sent
    const percent = snapshot.complete
        ? 100
        : snapshot.total > 0
          ? Math.min(99, Math.round((snapshot.loaded / snapshot.total) * 100))
          : 0;
    const withForm = !!schema.data;

    const batchFiles = (
        <ul className="flex flex-wrap gap-3" data-testid="batch-files">
            {snapshot.items.map(item => (
                <FileTile
                    key={item.id}
                    file={item.file}
                    status={item.status}
                    loaded={item.loaded}
                    error={errorMessage(item)}
                    onRetry={() => queue.retry(item.id)}
                    // The files being committed are already sent to the target
                    onRemove={
                        commitState.status === 'pending'
                            ? undefined
                            : () => removeItem(item)
                    }
                />
            ))}
        </ul>
    );

    const batchStatus = (
        <>
            <BatchProgress
                snapshot={snapshot}
                percent={percent}
                committing={commitState.status === 'pending'}
            />
            <BatchAlerts snapshot={snapshot} queue={queue} />
        </>
    );

    return (
        <PreviewCacheProvider files={uploadFiles}>
            <Page title={target.data.name}>
                {target.data.description ? (
                    <p
                        data-testid="target-description"
                        className="-mt-4 mb-6 text-sm whitespace-pre-line text-muted-foreground"
                    >
                        {target.data.description}
                    </p>
                ) : null}

                <Stepper
                    step={step}
                    withForm={withForm}
                    uploadPercent={snapshot.complete ? undefined : percent}
                />

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
                            withForm={withForm}
                        />
                    </>
                ) : null}

                {step === 'form' && schema.data ? (
                    <BatchDropzone
                        queue={queue}
                        snapshot={snapshot}
                        className="flex flex-col gap-4"
                    >
                        <p className="text-sm text-muted-foreground">
                            {snapshot.complete
                                ? t(
                                      'upload.form_hint_done',
                                      'Your files have arrived: only this information is left.'
                                  )
                                : t(
                                      'upload.form_hint',
                                      'Your files are being sent while you fill in this information.'
                                  )}
                        </p>
                        {batchStatus}
                        <div className="flex flex-wrap items-center gap-2">
                            <Button
                                variant="ghost"
                                size="sm"
                                data-testid="toggle-files"
                                aria-expanded={showFiles}
                                onClick={() => setShowFiles(v => !v)}
                            >
                                {showFiles ? (
                                    <ChevronUpIcon />
                                ) : (
                                    <ChevronDownIcon />
                                )}
                                {showFiles
                                    ? t('upload.hide_files', 'Hide the files')
                                    : t('upload.show_files', {
                                          defaultValue_one: 'Show the file',
                                          defaultValue_other:
                                              'Show the {{count}} files',
                                          count: snapshot.items.length,
                                      })}
                            </Button>
                            <AddFilesButton queue={queue} snapshot={snapshot} />
                        </div>
                        {showFiles ? batchFiles : null}
                        <div className="mt-2 border-t pt-6">
                            <MetadataFormStep
                                targetId={targetId}
                                schema={schema.data}
                                initialValues={formData}
                                onCancel={cancel}
                                onSubmit={values => {
                                    setFormData(values);
                                    setStep('progress');
                                }}
                            />
                        </div>
                    </BatchDropzone>
                ) : null}

                {step === 'progress' ? (
                    <div
                        className="flex flex-col gap-4"
                        data-testid="upload-progress"
                    >
                        {batchStatus}
                        {withForm &&
                        !snapshot.complete &&
                        commitState.status === 'idle' ? (
                            <div className="flex flex-wrap items-center justify-between gap-x-4 gap-y-2">
                                <p className="text-sm text-muted-foreground">
                                    {t(
                                        'upload.finalize_hint',
                                        'Your information is saved: the upload will be finalized as soon as every file has arrived.'
                                    )}
                                </p>
                                <Button
                                    size="sm"
                                    variant="outline"
                                    data-testid="edit-information"
                                    onClick={() => setStep('form')}
                                >
                                    <PencilIcon />{' '}
                                    {t(
                                        'upload.edit_information',
                                        'Edit the information'
                                    )}
                                </Button>
                            </div>
                        ) : null}
                        {batchFiles}
                        {commitState.status === 'error' ? (
                            <Alert
                                variant="destructive"
                                data-testid="commit-error"
                                title={t(
                                    'upload.commit_failed',
                                    'The upload could not be finalized'
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
                                                setCommitState({
                                                    status: 'idle',
                                                });
                                            }}
                                        >
                                            <RotateCwIcon />{' '}
                                            {t('upload.commit_retry', 'Retry')}
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
                    <div
                        className="flex flex-col gap-4"
                        data-testid="upload-done"
                    >
                        <div
                            role="status"
                            className="flex flex-col items-center gap-2 py-4 text-center"
                        >
                            <SuccessCheck />
                            <p className="mt-2 text-lg font-semibold">
                                {t('upload.done_title', {
                                    defaultValue_one: '{{count}} file sent',
                                    defaultValue_other: '{{count}} files sent',
                                    count: sent.files.length,
                                })}
                            </p>
                            <p className="text-sm text-muted-foreground">
                                {t('upload.done', {
                                    defaultValue_one:
                                        'Your file was delivered to {{target}}.',
                                    defaultValue_other:
                                        'Your files were delivered to {{target}}.',
                                    count: sent.files.length,
                                    target: target.data.name,
                                })}
                                {sent.notify
                                    ? ` ${t(
                                          'upload.done_notify',
                                          'You will be notified once they are processed.'
                                      )}`
                                    : null}
                            </p>
                        </div>
                        <ul
                            data-testid="sent-files"
                            className="divide-y rounded-md border text-sm"
                        >
                            {sent.files.map((f, i) => (
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
                        <div className="flex flex-wrap gap-2">
                            <Button data-testid="upload-again" onClick={reset}>
                                <UploadCloudIcon />{' '}
                                {t('upload.send_more', 'Send more files')}
                            </Button>
                            {targets.data && targets.data.length > 1 ? (
                                <Button variant="outline" asChild>
                                    <Link
                                        href={routes.home()}
                                        data-testid="other-target"
                                    >
                                        {t(
                                            'upload.other_target',
                                            'Choose another destination'
                                        )}
                                    </Link>
                                </Button>
                            ) : null}
                        </div>
                    </div>
                ) : null}
            </Page>
        </PreviewCacheProvider>
    );
}

function BatchProgress({
    snapshot,
    percent,
    committing,
}: {
    snapshot: QueueSnapshot;
    percent: number;
    committing: boolean;
}) {
    const {t} = useTranslation();
    const count = snapshot.items.length;
    const offline = snapshot.items.some(i => i.status === 'offline');

    const label = committing
        ? t('upload.committing', 'Finalizing the upload…')
        : offline
          ? t('upload.waiting_connection', 'Waiting for the connection…')
          : snapshot.complete
            ? t('upload.sent_count', {
                  defaultValue_one: '{{count}} file sent',
                  defaultValue_other: '{{count}} files sent',
                  count,
              })
            : t('upload.progress', {
                  defaultValue_one: '{{done}} of {{count}} file sent',
                  defaultValue_other: '{{done}} of {{count}} files sent',
                  done: snapshot.items.filter(i => i.status === 'done').length,
                  count,
              });

    return (
        <div className="flex flex-col gap-1.5" data-testid="batch-progress">
            <div className="flex flex-wrap justify-between gap-x-3 text-sm text-muted-foreground">
                <span>{label}</span>
                <span className="tabular-nums">
                    {formatFileSize(snapshot.loaded)} /{' '}
                    {formatFileSize(snapshot.total)}
                    {committing ? null : (
                        <>
                            {' · '}
                            <span
                                data-testid="batch-percent"
                                className="font-medium text-foreground"
                            >
                                {t('upload.percent', '{{percent}}%', {percent})}
                            </span>
                        </>
                    )}
                </span>
            </div>
            <Progress
                value={committing ? undefined : percent}
                indeterminate={committing}
                aria-label={t('upload.progress_label', 'Upload progress')}
            />
        </div>
    );
}

/** What needs the user: a lost connection, files that failed for good */
function BatchAlerts({
    snapshot,
    queue,
}: {
    snapshot: QueueSnapshot;
    queue: UploadQueue;
}) {
    const {t} = useTranslation();
    const confirm = useConfirm();
    const offline = snapshot.items.some(i => i.status === 'offline');

    const removeFailed = async () => {
        const count = snapshot.failed;
        if (
            await confirm({
                title: t('upload.remove_failed_title', {
                    defaultValue_one: 'Remove the failed file',
                    defaultValue_other: 'Remove the failed files',
                    count,
                }),
                description: t('upload.remove_failed_confirm', {
                    defaultValue_one: 'It will not be sent.',
                    defaultValue_other: 'They will not be sent.',
                    count,
                }),
                confirmLabel: t('upload.remove_failed', {
                    defaultValue_one: 'Remove the file',
                    defaultValue_other: 'Remove these files',
                    count,
                }),
                destructive: true,
            })
        ) {
            queue.removeFailed();
        }
    };

    return (
        <>
            {offline ? (
                <Alert
                    variant="warning"
                    icon={<WifiOffIcon />}
                    data-testid="batch-offline"
                >
                    {t(
                        'upload.offline',
                        'Connection lost. The upload will resume on its own as soon as it is back.'
                    )}
                </Alert>
            ) : null}
            {snapshot.failed > 0 ? (
                <Alert variant="warning" data-testid="batch-failed">
                    <div className="flex flex-col gap-3">
                        <span>
                            {t('upload.some_failed', {
                                defaultValue_one:
                                    '{{count}} file could not be sent.',
                                defaultValue_other:
                                    '{{count}} files could not be sent.',
                                count: snapshot.failed,
                            })}
                        </span>
                        <div className="flex flex-wrap gap-2">
                            <Button
                                size="sm"
                                variant="outline"
                                data-testid="retry-failed"
                                onClick={() => queue.retryFailed()}
                            >
                                <RotateCwIcon />{' '}
                                {t('upload.retry_failed', {
                                    defaultValue_one: 'Retry',
                                    defaultValue_other: 'Retry all',
                                    count: snapshot.failed,
                                })}
                            </Button>
                            <Button
                                size="sm"
                                variant="ghost"
                                data-testid="remove-failed"
                                onClick={removeFailed}
                            >
                                <XIcon />{' '}
                                {t('upload.remove_failed', {
                                    defaultValue_one: 'Remove the file',
                                    defaultValue_other: 'Remove these files',
                                    count: snapshot.failed,
                                })}
                            </Button>
                        </div>
                    </div>
                </Alert>
            ) : null}
        </>
    );
}

/** A dropzone adding files to a batch already being sent, within the same limits */
function useBatchFileDrop(
    queue: UploadQueue,
    snapshot: QueueSnapshot,
    options: Parameters<typeof useFileDrop>[2]
) {
    const {upload} = useConfig();
    const issueMessage = useBatchIssueMessage();

    return useFileDrop(
        snapshot.items.map(i => i.file),
        (files, added) => {
            const issues = checkBatch(files, upload);
            if (issues.length > 0) {
                issues.forEach(issue => toast.error(issueMessage(issue)));

                return;
            }
            queue.add(added);
        },
        {...options, disabled: upload.maxFileCount === 1}
    );
}

function AddFilesButton({
    queue,
    snapshot,
}: {
    queue: UploadQueue;
    snapshot: QueueSnapshot;
}) {
    const {t} = useTranslation();
    const {upload} = useConfig();
    const {getInputProps, open} = useBatchFileDrop(queue, snapshot, {
        noDrag: true,
    });

    if (upload.maxFileCount === 1) {
        return null;
    }

    return (
        <>
            <input {...getInputProps()} />
            <Button
                variant="ghost"
                size="sm"
                data-testid="add-files"
                onClick={open}
            >
                <PlusIcon /> {t('upload.add_files', 'Add files')}
            </Button>
        </>
    );
}

/** Files dropped anywhere on its content join the batch being sent */
function BatchDropzone({
    queue,
    snapshot,
    className,
    children,
}: {
    queue: UploadQueue;
    snapshot: QueueSnapshot;
    className?: string;
    children: ReactNode;
}) {
    const {t} = useTranslation();
    const {getRootProps, isDragActive} = useBatchFileDrop(queue, snapshot, {
        noClick: true,
        noKeyboard: true,
    });

    return (
        <div
            {...getRootProps({
                'data-testid': 'batch-dropzone',
                'className': cn('relative', className),
            } as React.HTMLAttributes<HTMLElement>)}
        >
            {children}
            {isDragActive ? (
                <div
                    aria-hidden
                    className="pointer-events-none absolute -inset-2 z-10 flex items-center justify-center rounded-lg border-2 border-dashed border-primary bg-background/90 text-muted-foreground"
                >
                    <div className="flex flex-col items-center gap-2 text-center">
                        <UploadCloudIcon className="size-10 text-primary" />
                        <p className="font-medium text-foreground">
                            {t(
                                'upload.drop_to_add',
                                'Drop the files to add them to the upload'
                            )}
                        </p>
                    </div>
                </div>
            ) : null}
        </div>
    );
}

function Stepper({
    step,
    withForm,
    uploadPercent,
}: {
    step: WizardStep;
    withForm: boolean;
    /** Progress of the files still being sent */
    uploadPercent?: number;
}) {
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
        {
            id: 'progress',
            // With a form, the files are mostly sent while it is filled in
            label: withForm
                ? t('upload.step.finalize', 'Finalization')
                : t('upload.step.progress', 'Upload'),
        },
        {id: 'done', label: t('upload.step.done', 'Done')},
    ];
    const current = steps.findIndex(s => s.id === step);
    const finished = step === 'done';

    return (
        <>
            <ol
                className="mb-6 flex flex-wrap items-center gap-2 text-sm"
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
                            data-testid={`stepper-mark-${s.id}`}
                            className={cn(
                                'flex size-6 items-center justify-center rounded-full border text-xs',
                                i < current &&
                                    'border-primary bg-primary text-primary-foreground',
                                i === current &&
                                    !finished &&
                                    'border-primary text-primary',
                                i === current &&
                                    finished &&
                                    'border-success bg-success text-success-foreground animate-in duration-500 zoom-in-50 motion-reduce:animate-none'
                            )}
                        >
                            {i < current || finished ? (
                                <CheckIcon className="size-3.5" />
                            ) : (
                                i + 1
                            )}
                        </span>
                        <span
                            className={cn(
                                i === current
                                    ? 'font-medium'
                                    : 'hidden text-muted-foreground sm:inline'
                            )}
                        >
                            {s.label}
                            {i === current &&
                            step === 'form' &&
                            uploadPercent !== undefined ? (
                                <span
                                    data-testid="stepper-upload"
                                    className="font-normal text-muted-foreground"
                                >
                                    {' · '}
                                    {t(
                                        'upload.step.uploading',
                                        'upload {{percent}}%',
                                        {percent: uploadPercent}
                                    )}
                                </span>
                            ) : null}
                        </span>
                    </li>
                ))}
            </ol>
            <p className="sr-only" aria-live="polite">
                {t(
                    'upload.step.announce',
                    'Step {{n}} of {{total}}: {{label}}',
                    {
                        n: current + 1,
                        total: steps.length,
                        label: steps[current]?.label,
                    }
                )}
            </p>
        </>
    );
}

/** A check drawing itself in a circle, at the end of the upload */
function SuccessCheck() {
    return (
        <div
            aria-hidden
            data-testid="success-animation"
            className="relative size-20 animate-in duration-300 fade-in zoom-in-50 motion-reduce:animate-none"
        >
            <span className="absolute inset-0 animate-success-ring rounded-full opacity-0 bg-success/30 motion-reduce:hidden" />
            <svg
                viewBox="0 0 52 52"
                className="relative size-full text-success"
                fill="none"
                stroke="currentColor"
                strokeWidth={3}
                strokeLinecap="round"
                strokeLinejoin="round"
            >
                <circle
                    cx="26"
                    cy="26"
                    r="24"
                    pathLength={1}
                    strokeDasharray={1}
                    className="animate-draw-stroke fill-success/10 motion-reduce:animate-none"
                />
                <path
                    d="M15 27l7 7 15-16"
                    pathLength={1}
                    strokeDasharray={1}
                    className="animate-draw-stroke [animation-delay:350ms] motion-reduce:animate-none"
                />
            </svg>
        </div>
    );
}
