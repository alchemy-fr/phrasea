'use client';

import {type FormEvent, useMemo, useState} from 'react';
import Link from 'next/link';
import {useRouter} from 'next/navigation';
import {useTranslation} from 'react-i18next';
import {useQuery, useQueryClient} from '@tanstack/react-query';
import {toast} from 'sonner';
import {ArrowLeftIcon, ImageIcon, PencilIcon, XIcon} from 'lucide-react';
import type {Publication} from '@/types/api';
import {Button} from '@alchemy/next-ui/ui/button';
import {FieldError, FieldHelp, Input, Label} from '@alchemy/next-ui/ui/input';
import {FullPageLoader} from '@alchemy/next-ui/ui/loader';
import {ErrorScreen} from '@/components/ErrorScreen';
import {useModals} from '@alchemy/next-ui/modals/ModalProvider';
import {Thumbnail} from '@/features/publication/Thumbnail';
import {
    getProfile,
    getPublication,
    patchPublication,
    profileKeys,
    publicationKeys,
} from '@/lib/api/publications';
import {ApiError, isApiError} from '@/lib/api/http';
import {toastError} from '@alchemy/next-ui/utils/errors';
import {useDirtyState} from '@alchemy/next-ui/navigation/unsavedChanges';
import {useTranslateField} from '@/lib/utils/translate';
import {routes} from '@/lib/routes';
import {ConfigForm} from './config/ConfigForm';
import {
    fromDraft,
    toDraft,
    validateDraft,
    type ConfigDraft,
} from './config/configDraft';
import {CoverDialog} from './CoverDialog';
import {ProfileSelect, PublicationSelect} from './selects';
import {violationsByField} from './violations';

type Values = {
    title: string;
    slug: string;
    parent: string | undefined;
    profile: string | undefined;
    cover: string | null;
    config: ConfigDraft;
};

function toValues(publication: Publication): Values {
    return {
        title: publication.title ?? '',
        slug: publication.slug ?? '',
        parent: publication.parent?.id ?? publication.parentId ?? undefined,
        profile: publication.profile?.id ?? undefined,
        cover: publication.cover?.id ?? null,
        config: toDraft(publication.config),
    };
}

export function PublicationEditScreen({id}: {id: string}) {
    const query = useQuery({
        queryKey: [...publicationKeys.detail(id), 'edit'],
        queryFn: () => getPublication(id),
        // Always the stored values, never what another page cached
        staleTime: 0,
    });

    if (query.isPending) {
        return <FullPageLoader />;
    }
    if (query.isError || !query.data) {
        return (
            <ErrorScreen error={query.error} onRetry={() => query.refetch()} />
        );
    }
    if (!query.data.capabilities?.edit) {
        return (
            <ErrorScreen error={new ApiError('Forbidden', 403, undefined)} />
        );
    }

    return <PublicationEditForm publication={query.data} />;
}

function PublicationEditForm({publication}: {publication: Publication}) {
    const {t} = useTranslation();
    const router = useRouter();
    const queryClient = useQueryClient();
    const {openModal} = useModals();
    const translate = useTranslateField();
    const initial = useMemo(() => toValues(publication), [publication]);
    const [values, setValues] = useState<Values>(initial);
    const [submitted, setSubmitted] = useState(false);
    const [saving, setSaving] = useState(false);
    const [serverErrors, setServerErrors] = useState<Record<string, string>>(
        {}
    );
    const {dirty, markSaved} = useDirtyState(values);
    const assets = publication.assets ?? [];
    const cover = assets.find(a => a.id === values.cover);

    const profile = useQuery({
        queryKey: profileKeys.detail(values.profile ?? ''),
        queryFn: () => getProfile(values.profile!),
        enabled: !!values.profile,
    });
    const profileDraft = useMemo(
        () => (profile.data ? toDraft(profile.data.config) : undefined),
        [profile.data]
    );

    const set = <K extends keyof Values>(key: K, v: Values[K]) =>
        setValues(prev => ({...prev, [key]: v}));
    const titleError = submitted && !values.title.trim();
    const configErrors = validateDraft(values.config);
    const back = routes.publication({
        id: publication.id,
        slug: values.slug || publication.slug,
    });

    const submit = async (e: FormEvent) => {
        e.preventDefault();
        setSubmitted(true);
        if (!values.title.trim() || Object.keys(configErrors).length > 0) {
            return;
        }
        setSaving(true);
        setServerErrors({});
        try {
            const saved = await patchPublication(publication.id, {
                title: values.title.trim(),
                slug: values.slug.trim() || null,
                parent: values.parent ? `/publications/${values.parent}` : null,
                profile: values.profile
                    ? `/publication-profiles/${values.profile}`
                    : null,
                // The API answers the first asset as the cover when there is
                // none: it is only sent once changed
                ...(values.cover !== initial.cover
                    ? {cover: values.cover ? `/assets/${values.cover}` : null}
                    : {}),
                config: fromDraft(values.config),
            });
            markSaved();
            await queryClient.invalidateQueries({
                queryKey: publicationKeys.all,
            });
            toast.success(t('publication.saved', 'Publication saved!'));
            router.push(routes.publication(saved));
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
            data-testid="publication-edit-form"
            className="mx-auto flex w-full max-w-3xl flex-col gap-6 px-4 py-6 sm:px-6"
        >
            <div className="flex items-center gap-2">
                <Button variant="ghost" size="icon-sm" asChild>
                    <Link href={back} aria-label={t('common.back', 'Back')}>
                        <ArrowLeftIcon />
                    </Link>
                </Button>
                <h1 className="min-w-0 truncate text-xl font-bold">
                    {t('publication.edit.title', 'Edit {{title}}', {
                        title: translate(publication, 'title'),
                    })}
                </h1>
            </div>

            <div className="flex flex-col gap-4">
                <div>
                    <Label htmlFor="publication-title" className="mb-1.5">
                        {t('publication.field.title', 'Title')} *
                    </Label>
                    <Input
                        id="publication-title"
                        data-testid="publication-title-input"
                        required
                        value={values.title}
                        aria-invalid={titleError || !!serverErrors.title}
                        onChange={e => set('title', e.target.value)}
                    />
                    <FieldError>
                        {titleError
                            ? t('form.required', 'This field is required.')
                            : serverErrors.title}
                    </FieldError>
                </div>
                <div>
                    <Label htmlFor="publication-slug" className="mb-1.5">
                        {t('publication.field.slug', 'Slug')}
                    </Label>
                    <Input
                        id="publication-slug"
                        data-testid="publication-slug-input"
                        value={values.slug}
                        aria-invalid={!!serverErrors.slug}
                        onChange={e => set('slug', e.target.value)}
                    />
                    <FieldHelp>
                        {t(
                            'publication.field.slug_help',
                            'Used in the URL of the publication instead of its id.'
                        )}
                    </FieldHelp>
                    <FieldError>{serverErrors.slug}</FieldError>
                </div>
                <div>
                    <Label htmlFor="publication-parent" className="mb-1.5">
                        {t('publication.field.parent', 'Parent publication')}
                    </Label>
                    <div className="flex gap-2">
                        <PublicationSelect
                            id="publication-parent"
                            value={values.parent}
                            onChange={v => set('parent', v)}
                            excluded={publication.id}
                            initial={publication.parent}
                        />
                        {values.parent ? (
                            <Button
                                variant="ghost"
                                size="icon"
                                aria-label={t('common.clear', 'Clear')}
                                onClick={() => set('parent', undefined)}
                            >
                                <XIcon />
                            </Button>
                        ) : null}
                    </div>
                    <FieldError>{serverErrors.parent}</FieldError>
                </div>
                <div>
                    <Label htmlFor="publication-profile" className="mb-1.5">
                        {t('publication.field.profile', 'Profile')}
                    </Label>
                    <div className="flex flex-wrap gap-2">
                        <ProfileSelect
                            id="publication-profile"
                            value={values.profile}
                            onChange={v => set('profile', v)}
                            initialLabel={publication.profile?.name}
                        />
                        {values.profile ? (
                            <>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t('common.clear', 'Clear')}
                                    onClick={() => set('profile', undefined)}
                                >
                                    <XIcon />
                                </Button>
                                <Button variant="outline" asChild>
                                    <Link
                                        href={routes.profileEdit(
                                            values.profile
                                        )}
                                        data-testid="edit-profile"
                                    >
                                        <PencilIcon />{' '}
                                        {t(
                                            'publication.edit_profile',
                                            'Edit profile'
                                        )}
                                    </Link>
                                </Button>
                            </>
                        ) : null}
                    </div>
                    <FieldHelp>
                        {t(
                            'publication.field.profile_help',
                            'The settings of the publication left unset are the ones of its profile.'
                        )}
                    </FieldHelp>
                </div>
                <div>
                    <Label className="mb-1.5">
                        {t('publication.field.cover', 'Cover')}
                    </Label>
                    <div className="flex items-center gap-3">
                        {cover ? (
                            <Thumbnail
                                asset={cover}
                                className="size-20 shrink-0 rounded-md"
                            />
                        ) : (
                            <span className="flex size-20 shrink-0 items-center justify-center rounded-md border border-dashed text-muted-foreground">
                                <ImageIcon className="size-6" />
                            </span>
                        )}
                        <Button
                            variant="outline"
                            data-testid="set-cover"
                            onClick={async () => {
                                const result = await openModal(CoverDialog, {
                                    assets,
                                    selected: values.cover,
                                });
                                if (result) {
                                    set('cover', result.cover);
                                }
                            }}
                        >
                            {t('publication.set_cover', 'Set cover image')}
                        </Button>
                    </div>
                </div>
            </div>

            <ConfigForm
                value={values.config}
                onChange={config => set('config', config)}
                profile={
                    values.profile ? (profileDraft ?? toDraft({})) : undefined
                }
                showErrors={submitted}
            />

            <div className="sticky bottom-0 -mx-4 flex justify-end gap-2 border-t bg-background/95 px-4 py-3 backdrop-blur sm:-mx-6 sm:px-6">
                <Button variant="outline" asChild>
                    <Link href={back}>{t('common.cancel', 'Cancel')}</Link>
                </Button>
                <Button
                    type="submit"
                    data-testid="publication-save"
                    loading={saving}
                    disabled={!dirty && !submitted}
                >
                    {t('publication.save', 'Save publication')}
                </Button>
            </div>
        </form>
    );
}
