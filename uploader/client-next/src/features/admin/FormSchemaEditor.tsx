'use client';

import {useId, useMemo, useState} from 'react';
import {useRouter} from 'next/navigation';
import {useMutation, useQueryClient} from '@tanstack/react-query';
import {useTranslation} from 'react-i18next';
import {toast} from 'sonner';
import {SaveIcon} from 'lucide-react';
import {Button} from '@/components/ui/button';
import {Alert} from '@/components/ui/misc';
import {FormRow, Input} from '@/components/ui/input';
import {SimpleSelect} from '@/components/ui/select';
import {CodeEditor} from '@/components/form/code/CodeEditor';
import {
    Dialog,
    DialogBody,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {iri, saveFormSchema} from '@/lib/api/uploader';
import {routes} from '@/lib/routes';
import {getErrorMessage} from '@/lib/utils/errors';
import {useDirtyState} from '@/lib/navigation/unsavedChanges';
import {LocaleMode, type FormSchema} from '@/types/api';
import {LiFormBody} from '@/features/liform/LiFormBody';
import {useLiForm} from '@/features/liform/useLiForm';
import type {LiFormSchema, LiFormValues} from '@/features/liform/types';
import {parseJsonObject, toJson} from './json';
import {TargetSelect} from './TargetSelect';
import {violationsByField} from './violations';

export const formSchemasQueryKey = ['form-schemas'] as const;

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

export function FormSchemaEditor({schema}: {schema?: FormSchema}) {
    const {t} = useTranslation();
    const router = useRouter();
    const queryClient = useQueryClient();
    const targetId = useId();
    const localeId = useId();
    const localeModeId = useId();
    const jsonId = useId();
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
    const {markSaved} = useDirtyState({target, locale, localeMode, json});

    const save = useMutation({
        mutationFn: () =>
            saveFormSchema(schema?.id, {
                target: iri('targets', target!),
                locale: locale.trim() || null,
                localeMode,
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

    const localeModes = [
        {
            value: String(LocaleMode.NoLocale),
            label: t('admin.locale_mode.none', 'No locale'),
        },
        {
            value: String(LocaleMode.UserAgent),
            label: t('admin.locale_mode.user_agent', "User's language"),
        },
        {
            value: String(LocaleMode.Forced),
            label: t('admin.locale_mode.forced', 'Forced to this locale'),
        },
    ];

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
                <FormRow
                    label={t('admin.form_schema.target', 'Target')}
                    htmlFor={targetId}
                    error={errors.target}
                >
                    <TargetSelect
                        id={targetId}
                        value={target}
                        onChange={setTarget}
                        disabled={save.isPending}
                    />
                </FormRow>
                <div className="grid gap-x-4 sm:grid-cols-2">
                    <FormRow
                        label={t('admin.form_schema.locale', 'Locale')}
                        htmlFor={localeId}
                        error={errors.locale}
                        help={t(
                            'admin.form_schema.locale_help',
                            'e.g. "en", "fr"; empty for every language.'
                        )}
                    >
                        <Input
                            id={localeId}
                            name="locale"
                            maxLength={5}
                            value={locale}
                            disabled={save.isPending}
                            onChange={e => setLocale(e.target.value)}
                        />
                    </FormRow>
                    <FormRow
                        label={t(
                            'admin.form_schema.locale_mode',
                            'Locale of the commit'
                        )}
                        htmlFor={localeModeId}
                        error={errors.localeMode}
                    >
                        <SimpleSelect<string>
                            id={localeModeId}
                            value={String(localeMode)}
                            options={localeModes}
                            disabled={save.isPending}
                            onValueChange={v =>
                                setLocaleMode(Number(v) as LocaleMode)
                            }
                        />
                    </FormRow>
                </div>
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
                <Button
                    type="submit"
                    loading={save.isPending}
                    data-testid="form-schema-save"
                >
                    <SaveIcon /> {t('admin.save', 'Save Form')}
                </Button>
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
