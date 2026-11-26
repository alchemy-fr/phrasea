'use client';

import {useState} from 'react';
import {useMutation, useQueryClient} from '@tanstack/react-query';
import {useTranslation} from 'react-i18next';
import {toast} from 'sonner';
import {Alert} from '@alchemy/ui/ui/misc';
import {Checkbox, LabeledControl} from '@alchemy/ui/ui/controls';
import {getErrorMessage} from '@alchemy/ui/utils/errors';
import {useDirtyState} from '@alchemy/ui/navigation/unsavedChanges';
import {updateTarget} from '@/lib/api/uploader';
import {targetsQueryKey} from '@/features/targets/TargetListScreen';
import type {TargetInput, TargetSettings} from '@/types/api';
import {targetSettingsQueryKey} from './queryKeys';
import {SaveBar} from './SaveBar';
import {
    TargetPropertiesFields,
    type TargetValues,
    toTargetInput,
} from './TargetPropertiesFields';
import {violationsByField} from './violations';

/** The authorization key is never read back: the field starts empty */
export function toTargetValues(settings: TargetSettings): TargetValues {
    return {
        name: settings.name,
        slug: settings.slug ?? '',
        description: settings.description ?? '',
        targetUrl: settings.targetUrl ?? '',
        authorizationScheme: settings.authorizationScheme ?? '',
        authorizationKey: '',
        defaultDestination: settings.defaultDestination ?? '',
        allowedGroups: settings.allowedGroups ?? [],
    };
}

/**
 * An empty authorization key leaves the saved one as it is, unless it is
 * removed. The API removes it as well once the target URL leads to another
 * origin without a new key.
 */
export function toTargetPatch(
    values: TargetValues,
    removeAuthorizationKey = false
): Partial<TargetInput> {
    const {authorizationKey, ...input} = toTargetInput(values);

    return input.targetUrl && !authorizationKey && !removeAuthorizationKey
        ? input
        : {
              ...input,
              authorizationKey: removeAuthorizationKey
                  ? null
                  : authorizationKey,
          };
}

/** The scheme, host and port of a URL: where its authorization key goes */
function urlOrigin(url: string | null | undefined): string | null {
    if (!url?.trim()) {
        return null;
    }
    try {
        return new URL(url.trim()).origin;
    } catch {
        return url.trim();
    }
}

/**
 * Whether the target URL leads to another origin than the saved one: the
 * saved key is then removed unless a new one is entered.
 */
export function isTargetOriginChanged(
    settings: TargetSettings,
    targetUrl: string
): boolean {
    return urlOrigin(settings.targetUrl) !== urlOrigin(targetUrl);
}

/**
 * The properties of a target (name, push settings, allowed groups…), only
 * for the admins.
 */
export function TargetPropertiesEditor({
    targetId,
    settings,
}: {
    targetId: string;
    settings: TargetSettings;
}) {
    // A new form once saved: it starts from what the API answered
    const [saved, setSaved] = useState({round: 0, settings});

    return (
        <PropertiesForm
            key={saved.round}
            targetId={targetId}
            settings={saved.settings}
            onSaved={result =>
                setSaved(s => ({round: s.round + 1, settings: result}))
            }
        />
    );
}

function PropertiesForm({
    targetId,
    settings,
    onSaved,
}: {
    targetId: string;
    settings: TargetSettings;
    onSaved: (result: TargetSettings) => void;
}) {
    const {t} = useTranslation();
    const queryClient = useQueryClient();
    const [values, setValues] = useState(() => toTargetValues(settings));
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [removeKey, setRemoveKey] = useState(false);
    const {dirty} = useDirtyState({values, removeKey});
    const originChanged = isTargetOriginChanged(settings, values.targetUrl);
    const removingKey = removeKey && !originChanged;
    const set = (patch: Partial<TargetValues>) =>
        setValues(v => ({...v, ...patch}));

    const save = useMutation({
        mutationFn: (input: Partial<TargetInput>) =>
            updateTarget(targetId, input),
        onSuccess: result => {
            queryClient.setQueryData(targetSettingsQueryKey(targetId), result);
            // The title and the breadcrumb show its name
            queryClient.setQueryData([...targetsQueryKey, targetId], result);
            void queryClient.invalidateQueries({queryKey: targetsQueryKey});
            toast.success(t('admin.target_properties.saved', 'Target saved'));
            onSaved(result);
        },
        onError: e => setErrors(violationsByField(e)),
    });

    return (
        <form
            data-testid="target-properties-editor"
            noValidate
            onSubmit={e => {
                e.preventDefault();
                setErrors({});
                save.mutate(toTargetPatch(values, removingKey));
            }}
        >
            <div className="max-w-3xl">
                <TargetPropertiesFields
                    values={values}
                    errors={errors}
                    set={set}
                    authorizationKey={
                        settings.hasAuthorizationKey
                            ? {
                                  placeholder:
                                      originChanged || removingKey
                                          ? undefined
                                          : t(
                                                'admin.target_properties.authorization_key_unchanged',
                                                'Unchanged'
                                            ),
                                  help: originChanged
                                      ? t(
                                            'admin.target_properties.authorization_key_lost',
                                            'The target URL leads to another host: enter the key again, otherwise it is removed.'
                                        )
                                      : undefined,
                                  disabled: removingKey,
                                  extra: originChanged ? null : (
                                      <div className="-mt-2 mb-4">
                                          <LabeledControl
                                              label={t(
                                                  'admin.target_properties.authorization_key_remove',
                                                  'Remove the key'
                                              )}
                                          >
                                              <Checkbox
                                                  data-testid="target-authorizationKey-remove"
                                                  checked={removingKey}
                                                  onCheckedChange={checked => {
                                                      setRemoveKey(
                                                          checked === true
                                                      );
                                                      set({
                                                          authorizationKey: '',
                                                      });
                                                  }}
                                              />
                                          </LabeledControl>
                                      </div>
                                  ),
                              }
                            : undefined
                    }
                />
            </div>
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
                disabled={!values.name.trim()}
            />
        </form>
    );
}
