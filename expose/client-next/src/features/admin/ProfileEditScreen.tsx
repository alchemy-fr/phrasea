'use client';

import {type FormEvent, useMemo, useState} from 'react';
import Link from 'next/link';
import {useRouter} from 'next/navigation';
import {useTranslation} from 'react-i18next';
import {useQuery, useQueryClient} from '@tanstack/react-query';
import {toast} from 'sonner';
import {ArrowLeftIcon} from 'lucide-react';
import type {PublicationProfile} from '@/types/api';
import {Button} from '@/components/ui/button';
import {FieldError, Input, Label} from '@/components/ui/input';
import {FullPageLoader} from '@/components/ui/loader';
import {ErrorScreen} from '@/components/ErrorScreen';
import {
    createProfile,
    getProfile,
    patchProfile,
    profileKeys,
    publicationKeys,
} from '@/lib/api/publications';
import {isApiError} from '@/lib/api/http';
import {toastError} from '@/lib/utils/errors';
import {useDirtyState} from '@/lib/navigation/unsavedChanges';
import {routes} from '@/lib/routes';
import {ConfigForm} from './config/ConfigForm';
import {
    fromDraft,
    toDraft,
    validateDraft,
    type ConfigDraft,
} from './config/configDraft';
import {violationsByField} from './violations';

/** Creation of a profile (no id), or edition of an existing one */
export function ProfileEditScreen({id}: {id?: string}) {
    const query = useQuery({
        queryKey: [...profileKeys.detail(id ?? ''), 'edit'],
        queryFn: () => getProfile(id!),
        enabled: !!id,
        staleTime: 0,
    });

    if (!id) {
        return <ProfileForm />;
    }
    if (query.isPending) {
        return <FullPageLoader />;
    }
    if (query.isError || !query.data) {
        return (
            <ErrorScreen error={query.error} onRetry={() => query.refetch()} />
        );
    }

    return <ProfileForm profile={query.data} />;
}

function ProfileForm({profile}: {profile?: PublicationProfile}) {
    const {t} = useTranslation();
    const router = useRouter();
    const queryClient = useQueryClient();
    const initial = useMemo(
        () => ({name: profile?.name ?? '', config: toDraft(profile?.config)}),
        [profile]
    );
    const [values, setValues] = useState<{name: string; config: ConfigDraft}>(
        initial
    );
    const [submitted, setSubmitted] = useState(false);
    const [saving, setSaving] = useState(false);
    const [serverErrors, setServerErrors] = useState<Record<string, string>>(
        {}
    );
    const {dirty, markSaved} = useDirtyState(values);
    const nameError = submitted && !values.name.trim();

    const submit = async (e: FormEvent) => {
        e.preventDefault();
        setSubmitted(true);
        if (
            !values.name.trim() ||
            Object.keys(validateDraft(values.config)).length > 0
        ) {
            return;
        }
        setSaving(true);
        setServerErrors({});
        try {
            const data = {
                name: values.name.trim(),
                config: fromDraft(values.config),
            };
            if (profile) {
                await patchProfile(profile.id, data);
            } else {
                await createProfile(data);
            }
            markSaved();
            await Promise.all([
                queryClient.invalidateQueries({queryKey: profileKeys.all}),
                // What the publications inherit changed
                queryClient.invalidateQueries({queryKey: publicationKeys.all}),
            ]);
            toast.success(
                profile
                    ? t('profile.saved', 'Profile saved!')
                    : t('profile.created', 'Profile created!')
            );
            router.push(routes.profiles());
        } catch (error) {
            if (isApiError(error, 422)) {
                setServerErrors(violationsByField(error));
            }
            toastError(error);
        } finally {
            setSaving(false);
        }
    };

    return (
        <form
            onSubmit={submit}
            noValidate
            data-testid="profile-form"
            className="mx-auto flex w-full max-w-3xl flex-col gap-6 px-4 py-6 sm:px-6"
        >
            <div className="flex items-center gap-2">
                <Button variant="ghost" size="icon-sm" asChild>
                    <Link
                        href={routes.profiles()}
                        aria-label={t('common.back', 'Back')}
                    >
                        <ArrowLeftIcon />
                    </Link>
                </Button>
                <h1 className="min-w-0 truncate text-xl font-bold">
                    {profile
                        ? t('profile.edit.title', 'Edit profile {{name}}', {
                              name: profile.name,
                          })
                        : t('profile.create.title', 'New profile')}
                </h1>
            </div>
            <div>
                <Label htmlFor="profile-name" className="mb-1.5">
                    {t('profile.field.name', 'Name')} *
                </Label>
                <Input
                    id="profile-name"
                    data-testid="profile-name-input"
                    required
                    autoFocus={!profile}
                    value={values.name}
                    aria-invalid={nameError || !!serverErrors.name}
                    onChange={e =>
                        setValues(v => ({...v, name: e.target.value}))
                    }
                />
                <FieldError>
                    {nameError
                        ? t('form.required', 'This field is required.')
                        : serverErrors.name}
                </FieldError>
            </div>
            <ConfigForm
                value={values.config}
                onChange={config => setValues(v => ({...v, config}))}
                showErrors={submitted}
            />
            <div className="sticky bottom-0 -mx-4 flex justify-end gap-2 border-t bg-background/95 px-4 py-3 backdrop-blur sm:-mx-6 sm:px-6">
                <Button variant="outline" asChild>
                    <Link href={routes.profiles()}>
                        {t('common.cancel', 'Cancel')}
                    </Link>
                </Button>
                <Button
                    type="submit"
                    data-testid="profile-save"
                    loading={saving}
                    disabled={!!profile && !dirty && !submitted}
                >
                    {t('profile.save', 'Save profile')}
                </Button>
            </div>
        </form>
    );
}
