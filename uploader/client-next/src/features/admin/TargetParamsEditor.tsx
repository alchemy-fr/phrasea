'use client';

import {useId, useMemo, useState} from 'react';
import {useRouter} from 'next/navigation';
import {useMutation, useQueryClient} from '@tanstack/react-query';
import {useTranslation} from 'react-i18next';
import {toast} from 'sonner';
import {SaveIcon} from 'lucide-react';
import {Button} from '@/components/ui/button';
import {Alert} from '@/components/ui/misc';
import {FormRow} from '@/components/ui/input';
import {CodeEditor} from '@/components/form/code/CodeEditor';
import {iri, saveTargetParams} from '@/lib/api/uploader';
import {routes} from '@/lib/routes';
import {getErrorMessage} from '@/lib/utils/errors';
import {useDirtyState} from '@/lib/navigation/unsavedChanges';
import type {TargetParams} from '@/types/api';
import {parseJsonObject, toJson} from './json';
import {TargetSelect} from './TargetSelect';
import {violationsByField} from './violations';

export const targetParamsQueryKey = ['target-params'] as const;

export const NEW_TARGET_PARAMS = {my_var: 'my_value'};

/**
 * Fixed data merged, on commit, into the form data of every batch sent to
 * the target (they take precedence over what users enter).
 */
export function TargetParamsEditor({params}: {params?: TargetParams}) {
    const {t} = useTranslation();
    const router = useRouter();
    const queryClient = useQueryClient();
    const targetId = useId();
    const jsonId = useId();
    const [target, setTarget] = useState(params?.target.id);
    const [json, setJson] = useState(() =>
        toJson(params?.data ?? NEW_TARGET_PARAMS)
    );
    const parsed = useMemo(() => parseJsonObject(json), [json]);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const {markSaved} = useDirtyState({target, json});

    const save = useMutation({
        mutationFn: () =>
            saveTargetParams(params?.id, {
                target: iri('targets', target!),
                data: parsed.value!,
            }),
        onSuccess: saved => {
            markSaved();
            void queryClient.invalidateQueries({
                queryKey: targetParamsQueryKey,
            });
            toast.success(t('admin.target_params.saved', 'Target data saved'));
            if (!params) {
                router.replace(routes.targetParamsEdit(saved.id));
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
        <form
            data-testid="target-params-editor"
            noValidate
            className="max-w-3xl"
            onSubmit={e => {
                e.preventDefault();
                submit();
            }}
        >
            <FormRow
                label={t('admin.target_params.target', 'Target')}
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
            <FormRow
                label={t('admin.target_params.data', 'Data (JSON)')}
                htmlFor={jsonId}
                error={
                    errors.data ??
                    (parsed.error
                        ? t(
                              'admin.error.invalid_json',
                              'Invalid JSON: {{error}}',
                              {
                                  error: parsed.error,
                              }
                          )
                        : undefined)
                }
                help={t(
                    'admin.target_params.help',
                    'Merged into the form data of every batch sent to this target; it takes precedence over what users enter.'
                )}
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
                data-testid="target-params-save"
            >
                <SaveIcon /> {t('admin.save', 'Save Form')}
            </Button>
        </form>
    );
}
