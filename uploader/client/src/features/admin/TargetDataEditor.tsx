'use client';

import {useId, useMemo, useState} from 'react';
import {useMutation, useQueryClient} from '@tanstack/react-query';
import {useTranslation} from 'react-i18next';
import {toast} from 'sonner';
import {Alert} from '@alchemy/next-ui/ui/misc';
import {FormRow} from '@alchemy/next-ui/ui/input';
import {getErrorMessage} from '@alchemy/next-ui/utils/errors';
import {useDirtyState} from '@alchemy/next-ui/navigation/unsavedChanges';
import {CodeEditor} from '@/components/form/code/CodeEditor';
import {saveTargetData} from '@/lib/api/uploader';
import {targetsQueryKey} from '@/features/targets/TargetListScreen';
import type {TargetData} from '@/types/api';
import {parseJsonObject, toJson, type JsonParseResult} from './json';
import {targetDataQueryKey} from './queryKeys';
import {SaveBar} from './SaveBar';
import {violationsByField} from './violations';

const EXAMPLE = toJson({my_var: 'my_value'});

/** An empty editor removes the data */
function parseData(json: string): JsonParseResult<TargetData | null> {
    return json.trim() === '' ? {value: null} : parseJsonObject(json);
}

/**
 * Fixed data merged, on commit, into the form data of every batch sent to
 * the target (they take precedence over what users enter).
 */
export function TargetDataEditor({
    targetId,
    data,
}: {
    targetId: string;
    data: TargetData | null;
}) {
    const {t} = useTranslation();
    const queryClient = useQueryClient();
    const jsonId = useId();
    const [json, setJson] = useState(() => (data ? toJson(data) : ''));
    const parsed = useMemo(() => parseData(json), [json]);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const {dirty, markSaved} = useDirtyState(json);

    const save = useMutation({
        mutationFn: (value: TargetData | null) =>
            saveTargetData(
                targetId,
                value && Object.keys(value).length > 0 ? value : null
            ),
        onSuccess: result => {
            markSaved();
            queryClient.setQueryData(targetDataQueryKey(targetId), result);
            void queryClient.invalidateQueries({queryKey: targetsQueryKey});
            toast.success(t('admin.target_data.saved', 'Target data saved'));
        },
        onError: e => setErrors(violationsByField(e)),
    });

    const invalidJson = parsed.error
        ? t('admin.error.invalid_json', 'Invalid JSON: {{error}}', {
              error: parsed.error,
          })
        : undefined;

    return (
        <form
            data-testid="target-data-editor"
            noValidate
            className="max-w-3xl"
            onSubmit={e => {
                e.preventDefault();
                setErrors({});
                if (parsed.error === undefined) {
                    save.mutate(parsed.value);
                }
            }}
        >
            <FormRow
                label={t('admin.target_data.data', 'Data (JSON)')}
                htmlFor={jsonId}
                error={errors.data ?? invalidJson}
                help={t(
                    'admin.target_data.help',
                    'Merged into the form data of every batch sent to this target; it takes precedence over what users enter. Leave empty for none.'
                )}
            >
                <CodeEditor
                    id={jsonId}
                    value={json}
                    onChange={setJson}
                    placeholder={EXAMPLE}
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
            <SaveBar
                dirty={dirty}
                saving={save.isPending}
                disabled={!!parsed.error}
            />
        </form>
    );
}
