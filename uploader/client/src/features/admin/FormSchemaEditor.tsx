'use client';

import {useId, useMemo, useState} from 'react';
import {useRouter} from 'next/navigation';
import {useMutation, useQueryClient} from '@tanstack/react-query';
import {useTranslation} from 'react-i18next';
import {toast} from 'sonner';
import {WandSparklesIcon} from 'lucide-react';
import {Button} from '@alchemy/next-ui/ui/button';
import {Alert} from '@alchemy/next-ui/ui/misc';
import {FormRow} from '@alchemy/next-ui/ui/input';
import {CodeEditor} from '@/components/form/code/CodeEditor';
import {
    Dialog,
    DialogBody,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@alchemy/next-ui/ui/dialog';
import {iri, saveFormSchema} from '@/lib/api/uploader';
import {routes} from '@/lib/routes';
import {getErrorMessage} from '@alchemy/next-ui/utils/errors';
import {useDirtyState} from '@alchemy/next-ui/navigation/unsavedChanges';
import {LocaleMode, type LiFormFormSchema} from '@/types/api';
import {LiFormBody} from '@/features/liform/LiFormBody';
import {useLiForm} from '@/features/liform/useLiForm';
import type {LiFormSchema, LiFormValues} from '@/features/liform/types';
import {parseJsonObject, toJson} from './json';
import type {FormDefinition} from '@/features/form-builder/types';
import {ConvertLiFormDialog} from './ConvertLiFormDialog';
import {FormSchemaMetaFields} from './FormSchemaMetaFields';
import {formSchemasQueryKey} from './queryKeys';
import {SaveBar} from './SaveBar';
import {violationsByField} from './violations';

export const NEW_FORM_SCHEMA: LiFormSchema = {
    type: 'object',
    required: [],
    properties: {
        my_field: {
            type: 'string',
            title: 'My field',
        },
    },
};

export function FormSchemaEditor({
    schema,
    onConvert,
}: {
    schema?: LiFormFormSchema;
    /** Opens the converted schema in the form builder */
    onConvert?: (definition: FormDefinition) => void;
}) {
    const {t} = useTranslation();
    const router = useRouter();
    const queryClient = useQueryClient();
    const jsonId = useId();
    const [converting, setConverting] = useState(false);
    const [target, setTarget] = useState(schema?.target.id);
    const [locale, setLocale] = useState(schema?.locale ?? '');
    const [localeMode, setLocaleMode] = useState<LocaleMode>(
        schema?.localeMode ?? LocaleMode.NoLocale
    );
    const [json, setJson] = useState(() =>
        toJson(schema?.data ?? NEW_FORM_SCHEMA)
    );
    const parsed = useMemo(() => parseJsonObject<LiFormSchema>(json), [json]);
    // The preview keeps the last valid schema while the JSON is being typed
    const [previewSchema, setPreviewSchema] = useState(parsed.value);
    if (parsed.value && parsed.value !== previewSchema) {
        setPreviewSchema(parsed.value);
    }
    const [errors, setErrors] = useState<Record<string, string>>({});
    const {dirty, markSaved} = useDirtyState({
        target,
        locale,
        localeMode,
        json,
    });

    const save = useMutation({
        mutationFn: () =>
            saveFormSchema(schema?.id, {
                target: iri('targets', target!),
                locale: locale.trim() || null,
                localeMode,
                format: 'liform',
                data: parsed.value!,
            }),
        onSuccess: saved => {
            markSaved();
            void queryClient.invalidateQueries({queryKey: formSchemasQueryKey});
            void queryClient.invalidateQueries({queryKey: ['targets']});
            toast.success(t('admin.form_schema.saved', 'Form schema saved'));
            if (!schema) {
                router.replace(routes.formSchemaEdit(saved.id));
            }
        },
        onError: e => setErrors(violationsByField(e)),
    });

    const submit = () => {
        const found: Record<string, string> = {};
        if (!target) {
            found.target = t('admin.error.required', 'This value is required.');
        }
        if (parsed.error) {
            found.data = t(
                'admin.error.invalid_json',
                'Invalid JSON: {{error}}',
                {
                    error: parsed.error,
                }
            );
        }
        setErrors(found);
        if (Object.keys(found).length === 0) {
            save.mutate();
        }
    };

    return (
        <div className="grid gap-8 lg:grid-cols-2">
            <form
                data-testid="form-schema-editor"
                noValidate
                onSubmit={e => {
                    e.preventDefault();
                    submit();
                }}
            >
                <FormSchemaMetaFields
                    value={{target, locale, localeMode}}
                    onChange={meta => {
                        setTarget(meta.target);
                        setLocale(meta.locale);
                        setLocaleMode(meta.localeMode);
                    }}
                    errors={errors}
                    disabled={save.isPending}
                />
                <FormRow
                    label={t('admin.form_schema.data', 'Schema (JSON)')}
                    htmlFor={jsonId}
                    error={
                        errors.data ??
                        (parsed.error
                            ? t(
                                  'admin.error.invalid_json',
                                  'Invalid JSON: {{error}}',
                                  {error: parsed.error}
                              )
                            : undefined)
                    }
                >
                    <CodeEditor
                        id={jsonId}
                        value={json}
                        onChange={setJson}
                        aria-invalid={!!parsed.error}
                    />
                </FormRow>
                {errors[''] ? (
                    <Alert variant="destructive" className="mb-4">
                        {errors['']}
                    </Alert>
                ) : null}
                {save.error && Object.keys(errors).length === 0 ? (
                    <Alert variant="destructive" className="mb-4">
                        {getErrorMessage(save.error)}
                    </Alert>
                ) : null}
                <SaveBar dirty={dirty} saving={save.isPending}>
                    {onConvert ? (
                        <Button
                            type="button"
                            variant="outline"
                            disabled={!parsed.value || save.isPending}
                            data-testid="form-schema-convert"
                            onClick={() => setConverting(true)}
                        >
                            <WandSparklesIcon />{' '}
                            {t(
                                'builder.convert.title',
                                'Convert to the form builder'
                            )}
                        </Button>
                    ) : null}
                </SaveBar>
                {onConvert && parsed.value ? (
                    <ConvertLiFormDialog
                        open={converting}
                        onOpenChange={setConverting}
                        schemaId={schema?.id}
                        targetId={target}
                        locale={locale.trim() || null}
                        liForm={parsed.value}
                        onConverted={onConvert}
                    />
                ) : null}
            </form>

            <section aria-labelledby={`${jsonId}-preview`}>
                <h2
                    id={`${jsonId}-preview`}
                    className="mb-4 text-lg font-semibold"
                >
                    {t('admin.form_schema.preview', 'Preview')}
                </h2>
                <div className="rounded-lg border p-4">
                    <SchemaPreview schema={previewSchema} />
                </div>
            </section>
        </div>
    );
}

/** The schema rendered as users will see it; submitting shows the data */
function SchemaPreview({schema}: {schema: LiFormSchema | undefined}) {
    const {t} = useTranslation();
    const form = useLiForm(schema);
    const [submitted, setSubmitted] = useState<LiFormValues>();

    return (
        <form
            data-testid="form-schema-preview"
            noValidate
            onSubmit={e => {
                e.preventDefault();
                const values = form.validate();
                if (values) {
                    setSubmitted(values);
                }
            }}
        >
            <LiFormBody form={form} />
            <Button type="submit" variant="outline">
                {t('admin.form_schema.test', 'Test the form')}
            </Button>
            <Dialog
                open={!!submitted}
                onOpenChange={open => !open && setSubmitted(undefined)}
            >
                <DialogContent size="lg">
                    <DialogHeader>
                        <DialogTitle>
                            {t('admin.form_schema.test_data', 'Submitted data')}
                        </DialogTitle>
                    </DialogHeader>
                    <DialogBody>
                        <pre
                            data-testid="form-schema-test-data"
                            className="max-h-[60vh] overflow-auto rounded-md bg-muted p-3 text-xs"
                        >
                            {toJson(submitted)}
                        </pre>
                    </DialogBody>
                </DialogContent>
            </Dialog>
        </form>
    );
}
