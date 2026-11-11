'use client';

import {useId, useMemo, useState} from 'react';
import {useMutation, useQueryClient} from '@tanstack/react-query';
import {useTranslation} from 'react-i18next';
import {toast} from 'sonner';
import {Trash2Icon} from 'lucide-react';
import {Button} from '@alchemy/next-ui/ui/button';
import {
    Alert,
    Tabs,
    TabsContent,
    TabsList,
    TabsTrigger,
} from '@alchemy/next-ui/ui/misc';
import {FormRow} from '@alchemy/next-ui/ui/input';
import {SimpleSelect} from '@alchemy/next-ui/ui/select';
import {Spinner} from '@alchemy/next-ui/ui/loader';
import {useConfirm} from '@alchemy/next-ui/ui/confirm';
import {
    Dialog,
    DialogBody,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@alchemy/next-ui/ui/dialog';
import {getErrorMessage} from '@alchemy/next-ui/utils/errors';
import {useDirtyState} from '@alchemy/next-ui/navigation/unsavedChanges';
import {saveTargetForm} from '@/lib/api/uploader';
import {languages} from '@/i18n/config';
import {targetsQueryKey} from '@/features/targets/TargetListScreen';
import {
    definitionProblems,
    emptyDefinition,
    isInputField,
} from '@/features/form-builder/definition';
import {FormBuilder} from '@/features/form-builder/editor/FormBuilder';
import {FormRenderer} from '@/features/form-builder/render/FormRenderer';
import {useBuilderForm} from '@/features/form-builder/render/useBuilderForm';
import type {
    FieldOptionsLoader,
    FormDefinition,
    FormValues,
} from '@/features/form-builder/types';
import {targetFormQueryKey} from './queryKeys';
import {
    useDataboxFields,
    useDataboxOptionsLoader,
    useDataboxWorkspaces,
    useTargetWorkspace,
} from './databox';
import {toJson} from './json';
import {SaveBar} from './SaveBar';
import {violationsByField} from './violations';

const NO_WORKSPACE = '__none__';

/**
 * Edits the form of a target, null when it has none yet. `onDeleted` is
 * called once the form is deleted.
 */
export function FormBuilderEditor({
    targetId,
    targetName,
    form,
    onDeleted,
}: {
    targetId: string;
    targetName: string;
    form: FormDefinition | null;
    onDeleted: () => void;
}) {
    const {t} = useTranslation();
    const confirm = useConfirm();
    const queryClient = useQueryClient();
    const workspaceSelectId = useId();
    const [definition, setDefinition] = useState<FormDefinition>(
        () => form ?? emptyDefinition()
    );
    const [saved, setSaved] = useState(form !== null);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const {dirty, markSaved} = useDirtyState(definition);

    const workspaces = useDataboxWorkspaces();
    const targetWorkspace = useTargetWorkspace(
        definition.workspaceId ? undefined : targetId
    );
    // A new form starts in the workspace the target uploads to
    if (
        !definition.workspaceId &&
        definition.fields.length === 0 &&
        targetWorkspace.data
    ) {
        setDefinition({...definition, workspaceId: targetWorkspace.data});
    }
    const workspaceId = definition.workspaceId ?? null;
    const workspace = workspaces.data?.find(w => w.id === workspaceId);
    const databoxFields = useDataboxFields(workspaceId);
    const loadOptions = useDataboxOptionsLoader(workspaceId);

    const locales = useMemo(() => {
        const list = workspace?.enabledLocales.length
            ? workspace.enabledLocales
            : languages.supportedLanguages.map(l => l.code);

        return [...new Set(list)];
    }, [workspace]);

    const issues = useMemo(() => {
        const messages: Record<string, string> = {
            invalid_key: t(
                'builder.issue.invalid_key',
                'Only letters, digits, "_", "-" and "." are allowed in a key.'
            ),
            duplicate_key: t(
                'builder.issue.duplicate_key',
                'Another field already has this key.'
            ),
            no_choice: t('builder.issue.no_choice', 'Add at least one choice.'),
            hidden_required: t(
                'builder.issue.hidden_required',
                'A hidden required field needs a default value.'
            ),
        };
        const found: Record<string, string> = Object.fromEntries(
            Object.entries(definitionProblems(definition)).map(([id, code]) => [
                id,
                messages[code],
            ])
        );
        if (databoxFields.data) {
            const slugs = new Set(databoxFields.data.map(f => f.slug));
            for (const field of definition.fields) {
                if (
                    isInputField(field) &&
                    field.source === 'attribute' &&
                    !found[field.id] &&
                    !slugs.has(field.attribute?.slug ?? field.key)
                ) {
                    found[field.id] = t(
                        'builder.issue.missing_attribute',
                        'The workspace has no such attribute: Databox will ignore the value.'
                    );
                }
            }
        }

        return found;
    }, [definition, databoxFields.data, t]);
    const blocking = Object.keys(definitionProblems(definition)).length > 0;

    const save = useMutation({
        mutationFn: () => saveTargetForm(targetId, definition),
        onSuccess: result => {
            markSaved();
            setSaved(true);
            queryClient.setQueryData(targetFormQueryKey(targetId), result);
            void queryClient.invalidateQueries({queryKey: targetsQueryKey});
            toast.success(t('admin.form.saved', 'Form saved'));
        },
        onError: e => setErrors(violationsByField(e)),
    });

    const submit = () => {
        setErrors({});
        if (!blocking) {
            save.mutate();
        }
    };

    const deleteForm = () =>
        void confirm({
            title: t('admin.form.delete_title', 'Delete the form'),
            description: t(
                'admin.form.delete_confirm',
                'The files sent to {{target}} will no longer come with any information.',
                {target: targetName}
            ),
            confirmLabel: t('admin.delete', 'Delete'),
            destructive: true,
            onConfirm: async () => {
                await saveTargetForm(targetId, null);
                queryClient.setQueryData(targetFormQueryKey(targetId), null);
                void queryClient.invalidateQueries({queryKey: targetsQueryKey});
                toast.success(t('admin.form.deleted', 'Form deleted'));
                onDeleted();
            },
        });

    const databoxStatus = !workspaceId ? (
        <p className="text-xs text-muted-foreground">
            {t(
                'builder.palette.no_workspace',
                'Choose a Databox workspace above.'
            )}
        </p>
    ) : databoxFields.isPending ? (
        <Spinner />
    ) : databoxFields.error ? (
        <p className="text-xs text-destructive">
            {getErrorMessage(databoxFields.error)}
        </p>
    ) : undefined;

    return (
        <form
            data-testid="form-builder-editor"
            noValidate
            onSubmit={e => {
                e.preventDefault();
                submit();
            }}
        >
            <div className="max-w-md">
                <FormRow
                    label={t('builder.workspace', 'Databox workspace')}
                    htmlFor={workspaceSelectId}
                    error={
                        errors['form.workspaceId'] ??
                        (workspaces.error
                            ? getErrorMessage(workspaces.error)
                            : undefined)
                    }
                    help={t(
                        'builder.workspace_help',
                        'The attributes offered by the form. Leave empty for a target other than Databox.'
                    )}
                >
                    <SimpleSelect<string>
                        id={workspaceSelectId}
                        value={workspaceId ?? NO_WORKSPACE}
                        disabled={save.isPending || workspaces.isPending}
                        onValueChange={v =>
                            setDefinition({
                                ...definition,
                                workspaceId: v === NO_WORKSPACE ? null : v,
                            })
                        }
                        options={[
                            {
                                value: NO_WORKSPACE,
                                label: t('builder.no_workspace', 'None'),
                            },
                            ...(workspaces.data ?? []).map(w => ({
                                value: w.id,
                                label: w.name,
                            })),
                            // A workspace the user cannot see any more
                            ...(workspaceId && workspaces.data && !workspace
                                ? [{value: workspaceId, label: workspaceId}]
                                : []),
                        ]}
                    />
                </FormRow>
            </div>

            <Tabs defaultValue="build" className="mb-4">
                <TabsList>
                    <TabsTrigger value="build">
                        {t('builder.tab.build', 'Fields')}
                    </TabsTrigger>
                    <TabsTrigger
                        value="preview"
                        data-testid="form-builder-preview-tab"
                    >
                        {t('admin.form.preview', 'Preview')}
                    </TabsTrigger>
                </TabsList>
                <TabsContent value="build" className="pt-3">
                    <FormBuilder
                        definition={definition}
                        onChange={setDefinition}
                        databoxFields={databoxFields.data}
                        databoxStatus={databoxStatus}
                        locales={locales}
                        loadOptions={loadOptions}
                        issues={issues}
                        disabled={save.isPending}
                    />
                </TabsContent>
                <TabsContent value="preview" className="pt-3">
                    <div className="mx-auto max-w-2xl rounded-lg border p-4">
                        <BuilderPreview
                            definition={definition}
                            loadOptions={loadOptions}
                        />
                    </div>
                </TabsContent>
            </Tabs>

            {errors[''] || errors.form ? (
                <Alert variant="destructive" className="mb-4">
                    {errors[''] ?? errors.form}
                </Alert>
            ) : null}
            {Object.entries(errors)
                .filter(([path]) => path.startsWith('form.fields'))
                .map(([path, message]) => (
                    <Alert key={path} variant="destructive" className="mb-4">
                        {path}: {message}
                    </Alert>
                ))}
            {save.error && Object.keys(errors).length === 0 ? (
                <Alert variant="destructive" className="mb-4">
                    {getErrorMessage(save.error)}
                </Alert>
            ) : null}
            <SaveBar
                dirty={dirty}
                saving={save.isPending}
                disabled={blocking}
                className="-mx-4 px-4"
            >
                {saved ? (
                    <Button
                        type="button"
                        variant="ghost"
                        data-testid="form-delete"
                        disabled={save.isPending}
                        onClick={deleteForm}
                    >
                        <Trash2Icon />{' '}
                        {t('admin.form.delete', 'Delete the form')}
                    </Button>
                ) : null}
            </SaveBar>
        </form>
    );
}

/** The form as users will see it; submitting shows the data */
function BuilderPreview({
    definition,
    loadOptions,
}: {
    definition: FormDefinition;
    loadOptions: FieldOptionsLoader;
}) {
    const {t} = useTranslation();
    const form = useBuilderForm(definition);
    const [submitted, setSubmitted] = useState<FormValues>();

    return (
        <div data-testid="form-preview">
            <FormRenderer
                definition={definition}
                values={form.values}
                onChange={form.setValues}
                errors={form.errors}
                loadOptions={loadOptions}
            />
            <Button
                type="button"
                variant="outline"
                onClick={() => {
                    const values = form.validate();
                    if (values) {
                        setSubmitted(values);
                    }
                }}
            >
                {t('admin.form.test', 'Test the form')}
            </Button>
            <Dialog
                open={!!submitted}
                onOpenChange={open => !open && setSubmitted(undefined)}
            >
                <DialogContent size="lg">
                    <DialogHeader>
                        <DialogTitle>
                            {t('admin.form.test_data', 'Submitted data')}
                        </DialogTitle>
                    </DialogHeader>
                    <DialogBody>
                        <pre
                            data-testid="form-test-data"
                            className="max-h-[60vh] overflow-auto rounded-md bg-muted p-3 text-xs"
                        >
                            {toJson(submitted)}
                        </pre>
                    </DialogBody>
                </DialogContent>
            </Dialog>
        </div>
    );
}
