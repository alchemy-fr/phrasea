'use client';

import {type ReactNode, useId, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {
    EyeIcon,
    EyeOffIcon,
    GalleryHorizontalIcon,
    LayoutGridIcon,
    ListIcon,
    MapIcon,
} from 'lucide-react';
import {Layout, SecurityMethod, type TermsConfig} from '@/types/api';
import {Switch, RadioGroup, RadioGroupItem} from '@/components/ui/controls';
import {
    FieldError,
    FieldHelp,
    Input,
    Label,
    Textarea,
} from '@/components/ui/input';
import {Badge} from '@/components/ui/misc';
import {Button} from '@/components/ui/button';
import {formatDateTime} from '@/lib/utils/format';
import {cn} from '@/lib/utils/cn';
import {
    overrideValue,
    PUBLIC_METHOD,
    validateDraft,
    type ConfigDraft,
    type InheritableField,
} from './configDraft';
import {fromLocalInput, toLocalInput} from './dates';

type Props = {
    value: ConfigDraft;
    onChange: (value: ConfigDraft) => void;
    /**
     * The configuration of the profile of the publication: each field then
     * inherits the value of the profile until the user overrides it.
     */
    profile?: ConfigDraft;
    /** Shows the errors of the fields (once a save was attempted) */
    showErrors?: boolean;
};

/**
 * The configuration of a publication or of a profile: visibility, dates,
 * downloads, layout, security and terms.
 */
export function ConfigForm({value, onChange, profile, showErrors}: Props) {
    const {t} = useTranslation();
    const errors = showErrors ? validateDraft(value) : {};
    const set = <K extends keyof ConfigDraft>(key: K, v: ConfigDraft[K]) =>
        onChange({...value, [key]: v});
    const inheritable = <F extends InheritableField>(field: F) => ({
        field,
        value,
        profile,
        onChange,
    });

    return (
        <div className="flex flex-col gap-6" data-testid="config-form">
            <Section title={t('config.section.visibility', 'Visibility')}>
                <Inheritable
                    {...inheritable('enabled')}
                    label={t('config.enabled', 'Enabled')}
                    format={v => yesNo(t, v)}
                >
                    {v => (
                        <SwitchField
                            id="config-enabled"
                            checked={!!v}
                            onChange={c => set('enabled', c)}
                            label={t('config.enabled', 'Enabled')}
                        />
                    )}
                </Inheritable>
                <Inheritable
                    {...inheritable('publiclyListed')}
                    label={t('config.publicly_listed', 'Publicly listed')}
                    format={v => yesNo(t, v)}
                    help={t(
                        'config.publicly_listed_help',
                        'Listed on the home page, for everyone.'
                    )}
                >
                    {v => (
                        <SwitchField
                            id="config-publicly-listed"
                            checked={!!v}
                            onChange={c => set('publiclyListed', c)}
                            label={t(
                                'config.publicly_listed',
                                'Publicly listed'
                            )}
                        />
                    )}
                </Inheritable>
                <div className="grid gap-4 sm:grid-cols-2">
                    <Inheritable
                        {...inheritable('beginsAt')}
                        label={t('config.begins_at', 'Begins at')}
                        format={v =>
                            formatDate(v) || t('config.no_date', 'No date')
                        }
                    >
                        {v => (
                            <DateTimeField
                                id="config-begins-at"
                                label={t('config.begins_at', 'Begins at')}
                                value={v}
                                onChange={d => set('beginsAt', d)}
                                clearable={!profile}
                            />
                        )}
                    </Inheritable>
                    <Inheritable
                        {...inheritable('expiresAt')}
                        label={t('config.expires_at', 'Expires at')}
                        format={v =>
                            formatDate(v) || t('config.no_date', 'No date')
                        }
                        error={
                            errors.expiresAt
                                ? t(
                                      'config.error.expires_before_begin',
                                      'The end must come after the beginning.'
                                  )
                                : undefined
                        }
                    >
                        {v => (
                            <DateTimeField
                                id="config-expires-at"
                                label={t('config.expires_at', 'Expires at')}
                                value={v}
                                onChange={d => set('expiresAt', d)}
                                clearable={!profile}
                            />
                        )}
                    </Inheritable>
                </div>
            </Section>

            <Section title={t('config.section.download', 'Download')}>
                <Inheritable
                    {...inheritable('downloadEnabled')}
                    label={t('config.download_enabled', 'Download enabled')}
                    format={v => yesNo(t, v)}
                >
                    {v => (
                        <SwitchField
                            id="config-download-enabled"
                            checked={!!v}
                            onChange={c => set('downloadEnabled', c)}
                            label={t(
                                'config.download_enabled',
                                'Download enabled'
                            )}
                        />
                    )}
                </Inheritable>
                <Inheritable
                    {...inheritable('downloadViaEmail')}
                    label={t('config.download_via_email', 'Download via email')}
                    format={v => yesNo(t, v)}
                    help={t(
                        'config.download_via_email_help',
                        'The visitor types an email address and receives the download link there.'
                    )}
                >
                    {v => (
                        <SwitchField
                            id="config-download-via-email"
                            checked={!!v}
                            onChange={c => set('downloadViaEmail', c)}
                            label={t(
                                'config.download_via_email',
                                'Download via email'
                            )}
                        />
                    )}
                </Inheritable>
                <Inheritable
                    {...inheritable('includeDownloadTermsInArchive')}
                    label={t(
                        'config.include_download_terms',
                        'Include the download terms in the archive'
                    )}
                    format={v => yesNo(t, v)}
                >
                    {v => (
                        <SwitchField
                            id="config-include-download-terms"
                            checked={!!v}
                            onChange={c =>
                                set('includeDownloadTermsInArchive', c)
                            }
                            label={t(
                                'config.include_download_terms',
                                'Include the download terms in the archive'
                            )}
                        />
                    )}
                </Inheritable>
            </Section>

            <Section title={t('config.section.layout', 'Layout')}>
                <Inheritable
                    {...inheritable('layout')}
                    label={t('config.layout', 'Layout')}
                    format={v => layoutLabel(t, v)}
                >
                    {v => (
                        <LayoutField
                            value={v ?? Layout.Grid}
                            onChange={l => set('layout', l)}
                        />
                    )}
                </Inheritable>
            </Section>

            <Section title={t('config.section.security', 'Security')}>
                <Inheritable
                    {...inheritable('securityMethod')}
                    label={t('config.security_method', 'Access')}
                    format={v => securityLabel(t, v)}
                >
                    {v => (
                        <SecurityField
                            method={v ?? (profile ? PUBLIC_METHOD : null)}
                            password={value.password}
                            passwordError={
                                errors.password
                                    ? t(
                                          'config.error.password_required',
                                          'Type the password of the publication.'
                                      )
                                    : undefined
                            }
                            onMethodChange={m =>
                                set(
                                    'securityMethod',
                                    // A profile has nothing to override
                                    m === PUBLIC_METHOD && !profile ? null : m
                                )
                            }
                            onPasswordChange={p => set('password', p)}
                        />
                    )}
                </Inheritable>
            </Section>

            <Section title={t('config.section.terms', 'Terms')}>
                <Inheritable
                    {...inheritable('terms')}
                    label={t('config.terms', 'Terms and conditions')}
                    format={v => termsLabel(t, v)}
                >
                    {v => (
                        <TermsField
                            id="config-terms"
                            label={t(
                                'config.terms_enabled',
                                'Enable terms and conditions'
                            )}
                            value={v}
                            onChange={terms => set('terms', terms)}
                        />
                    )}
                </Inheritable>
                <Inheritable
                    {...inheritable('downloadTerms')}
                    label={t('config.download_terms', 'Download terms')}
                    format={v => termsLabel(t, v)}
                >
                    {v => (
                        <TermsField
                            id="config-download-terms"
                            label={t(
                                'config.download_terms_enabled',
                                'Enable download terms'
                            )}
                            value={v}
                            onChange={terms => set('downloadTerms', terms)}
                        />
                    )}
                </Inheritable>
            </Section>
        </div>
    );
}

type T = ReturnType<typeof useTranslation>['t'];

function yesNo(t: T, v: boolean | null): string {
    return v ? t('common.yes', 'Yes') : t('common.no', 'No');
}

function formatDate(v: string | null): string {
    return v ? formatDateTime(v, 'medium') : '';
}

function layoutLabel(t: T, v: Layout | null): string {
    switch (v ?? Layout.Grid) {
        case Layout.Gallery:
            return t('config.layout.gallery', 'Gallery');
        case Layout.Download:
            return t('config.layout.download', 'Download');
        case Layout.Mapbox:
            return t('config.layout.mapbox', 'Map');
        default:
            return t('config.layout.grid', 'Grid');
    }
}

function securityLabel(t: T, v: ConfigDraft['securityMethod']): string {
    switch (v) {
        case SecurityMethod.Password:
            return t('config.security.password', 'Password');
        case SecurityMethod.Authentication:
            return t('config.security.authentication', 'Authentication');
        default:
            return t('config.security.public', 'Public');
    }
}

function termsLabel(t: T, v: TermsConfig | null): string {
    return v?.enabled
        ? t('config.terms_on', 'Enabled')
        : t('config.terms_off', 'Disabled');
}

function Section({title, children}: {title: ReactNode; children: ReactNode}) {
    return (
        <fieldset className="flex flex-col gap-4 rounded-lg border p-4">
            <legend className="px-1 text-sm font-semibold">{title}</legend>
            {children}
        </fieldset>
    );
}

/**
 * A field of the configuration of a publication linked to a profile: the
 * value of the profile, read-only, until "Override" is turned on. Without a
 * profile, the field alone.
 */
function Inheritable<F extends InheritableField>({
    field,
    value,
    profile,
    onChange,
    label,
    format,
    help,
    error,
    children,
}: {
    field: F;
    value: ConfigDraft;
    profile?: ConfigDraft;
    onChange: (value: ConfigDraft) => void;
    label: ReactNode;
    format: (v: ConfigDraft[F]) => ReactNode;
    help?: ReactNode;
    error?: ReactNode;
    children: (v: ConfigDraft[F]) => ReactNode;
}) {
    const {t} = useTranslation();
    const id = useId();
    const own = value[field];

    if (!profile) {
        return (
            <div data-field={field}>
                {children(own)}
                <FieldHelp>{help}</FieldHelp>
                <FieldError>{error}</FieldError>
            </div>
        );
    }

    const overridden = own !== null;

    return (
        <div
            data-field={field}
            data-overridden={overridden || undefined}
            className="rounded-md border border-dashed p-3 data-overridden:border-solid data-overridden:border-primary/40"
        >
            <div className="mb-2 flex flex-wrap items-center justify-between gap-2">
                <span className="text-sm font-medium">{label}</span>
                <label
                    htmlFor={id}
                    className="flex cursor-pointer items-center gap-2 text-xs text-muted-foreground"
                >
                    {t('config.override', 'Override settings')}
                    <Switch
                        id={id}
                        data-testid={`override-${field}`}
                        checked={overridden}
                        onCheckedChange={on =>
                            onChange({
                                ...value,
                                [field]: on
                                    ? overrideValue(field, profile)
                                    : null,
                            })
                        }
                    />
                </label>
            </div>
            {overridden ? (
                children(own)
            ) : (
                <p className="text-sm text-muted-foreground">
                    {t('config.inherited', 'From the profile:')}{' '}
                    <span
                        data-testid={`inherited-${field}`}
                        className="font-medium text-foreground"
                    >
                        {format(profile[field])}
                    </span>
                </p>
            )}
            <FieldHelp>{help}</FieldHelp>
            <FieldError>{error}</FieldError>
        </div>
    );
}

function SwitchField({
    id,
    label,
    checked,
    onChange,
}: {
    id: string;
    label: ReactNode;
    checked: boolean;
    onChange: (checked: boolean) => void;
}) {
    return (
        <div className="flex items-center gap-3">
            <Switch
                id={id}
                data-testid={id}
                checked={checked}
                onCheckedChange={onChange}
            />
            <Label htmlFor={id}>{label}</Label>
        </div>
    );
}

function DateTimeField({
    id,
    label,
    value,
    onChange,
    clearable,
}: {
    id: string;
    label: ReactNode;
    value: string | null;
    onChange: (value: string | null) => void;
    clearable: boolean;
}) {
    const {t} = useTranslation();

    return (
        <div>
            <Label htmlFor={id} className="mb-1.5">
                {label}
            </Label>
            <div className="flex gap-2">
                <Input
                    id={id}
                    data-testid={id}
                    type="datetime-local"
                    value={toLocalInput(value)}
                    onChange={e => {
                        const next = fromLocalInput(e.target.value);
                        if (next || clearable) {
                            onChange(next);
                        }
                    }}
                />
                {clearable && value ? (
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => onChange(null)}
                    >
                        {t('common.clear', 'Clear')}
                    </Button>
                ) : null}
            </div>
        </div>
    );
}

function LayoutField({
    value,
    onChange,
}: {
    value: Layout;
    onChange: (value: Layout) => void;
}) {
    const {t} = useTranslation();
    const layouts = [
        {value: Layout.Grid, icon: <LayoutGridIcon />},
        {value: Layout.Gallery, icon: <GalleryHorizontalIcon />},
        {value: Layout.Download, icon: <ListIcon />},
        {value: Layout.Mapbox, icon: <MapIcon />, soon: true},
    ];

    return (
        <RadioGroup
            value={value}
            onValueChange={v => onChange(v as Layout)}
            aria-label={t('config.layout', 'Layout')}
            className="grid grid-cols-2 gap-2 sm:grid-cols-4"
        >
            {layouts.map(layout => (
                <label
                    key={layout.value}
                    data-testid={`layout-${layout.value}`}
                    className={cn(
                        'relative flex cursor-pointer flex-col items-center gap-2 rounded-md border p-3 text-sm transition-colors hover:bg-accent has-[[data-state=checked]]:border-primary has-[[data-state=checked]]:bg-primary/5 [&>svg]:size-6',
                        layout.soon && 'pointer-events-none opacity-50'
                    )}
                >
                    <RadioGroupItem
                        value={layout.value}
                        disabled={layout.soon}
                        className="sr-only"
                    />
                    {layout.icon}
                    {layoutLabel(t, layout.value)}
                    {layout.soon ? (
                        <Badge
                            variant="secondary"
                            className="absolute top-1 right-1"
                        >
                            {t('config.soon', 'Soon')}
                        </Badge>
                    ) : null}
                </label>
            ))}
        </RadioGroup>
    );
}

function SecurityField({
    method,
    password,
    passwordError,
    onMethodChange,
    onPasswordChange,
}: {
    method: ConfigDraft['securityMethod'];
    password: string;
    passwordError?: string;
    onMethodChange: (method: SecurityMethod | '') => void;
    onPasswordChange: (password: string) => void;
}) {
    const {t} = useTranslation();
    const [visible, setVisible] = useState(false);
    const methods: {value: SecurityMethod | ''; label: string; help: string}[] =
        [
            {
                value: PUBLIC_METHOD,
                label: t('config.security.public', 'Public'),
                help: t('config.security.public_help', 'Anyone with the link.'),
            },
            {
                value: SecurityMethod.Authentication,
                label: t('config.security.authentication', 'Authentication'),
                help: t(
                    'config.security.authentication_help',
                    'Signed-in users allowed to view the publication.'
                ),
            },
            {
                value: SecurityMethod.Password,
                label: t('config.security.password', 'Password'),
                help: t(
                    'config.security.password_help',
                    'Anyone who knows the password, for the publication and its sub-publications.'
                ),
            },
        ];

    return (
        <div className="flex flex-col gap-3">
            <RadioGroup
                value={method ?? PUBLIC_METHOD}
                onValueChange={v => onMethodChange(v as SecurityMethod | '')}
                aria-label={t('config.security_method', 'Access')}
                className="flex flex-col gap-2"
            >
                {methods.map(m => (
                    <label
                        key={m.value || 'public'}
                        data-testid={`security-${m.value || 'public'}`}
                        className="flex cursor-pointer items-start gap-3 rounded-md border p-3 hover:bg-accent has-[[data-state=checked]]:border-primary has-[[data-state=checked]]:bg-primary/5"
                    >
                        <RadioGroupItem value={m.value} className="mt-0.5" />
                        <span>
                            <span className="block text-sm font-medium">
                                {m.label}
                            </span>
                            <span className="block text-xs text-muted-foreground">
                                {m.help}
                            </span>
                        </span>
                    </label>
                ))}
            </RadioGroup>
            {method === SecurityMethod.Password ? (
                <div>
                    <Label htmlFor="config-password" className="mb-1.5">
                        {t('config.password', 'Password')}
                    </Label>
                    <div className="flex gap-2">
                        <Input
                            id="config-password"
                            data-testid="config-password"
                            type={visible ? 'text' : 'password'}
                            autoComplete="new-password"
                            value={password}
                            aria-invalid={!!passwordError}
                            onChange={e => onPasswordChange(e.target.value)}
                        />
                        <Button
                            variant="outline"
                            size="icon"
                            aria-label={
                                visible
                                    ? t(
                                          'config.password_hide',
                                          'Hide the password'
                                      )
                                    : t(
                                          'config.password_show',
                                          'Show the password'
                                      )
                            }
                            onClick={() => setVisible(v => !v)}
                        >
                            {visible ? <EyeOffIcon /> : <EyeIcon />}
                        </Button>
                    </div>
                    <FieldError>{passwordError}</FieldError>
                </div>
            ) : null}
        </div>
    );
}

function TermsField({
    id,
    label,
    value,
    onChange,
}: {
    id: string;
    label: ReactNode;
    value: TermsConfig | null;
    onChange: (value: TermsConfig) => void;
}) {
    const {t} = useTranslation();
    const enabled = !!value?.enabled;

    return (
        <div className="flex flex-col gap-3">
            <SwitchField
                id={`${id}-enabled`}
                label={label}
                checked={enabled}
                onChange={on =>
                    onChange(
                        on
                            ? {
                                  enabled: true,
                                  text: value?.text ?? null,
                                  url: value?.url ?? null,
                              }
                            : {enabled: false, text: null, url: null}
                    )
                }
            />
            {enabled ? (
                <>
                    <div>
                        <Label htmlFor={`${id}-text`} className="mb-1.5">
                            {t('config.terms_text', 'Terms content')}
                        </Label>
                        <Textarea
                            id={`${id}-text`}
                            data-testid={`${id}-text`}
                            rows={5}
                            value={value?.text ?? ''}
                            onChange={e =>
                                onChange({
                                    ...value,
                                    enabled: true,
                                    text: e.target.value || null,
                                })
                            }
                        />
                        <FieldHelp>
                            {t('config.terms_text_help', 'HTML is accepted.')}
                        </FieldHelp>
                    </div>
                    <div>
                        <Label htmlFor={`${id}-url`} className="mb-1.5">
                            {t('config.terms_url', 'Terms URL')}
                        </Label>
                        <Input
                            id={`${id}-url`}
                            data-testid={`${id}-url`}
                            type="url"
                            placeholder="https://"
                            value={value?.url ?? ''}
                            onChange={e =>
                                onChange({
                                    ...value,
                                    enabled: true,
                                    url: e.target.value || null,
                                })
                            }
                        />
                    </div>
                </>
            ) : null}
        </div>
    );
}
