'use client';

import {useEffect, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {useTheme} from 'next-themes';
import {useQuery, useQueryClient} from '@tanstack/react-query';
import {
    CheckIcon,
    MoonIcon,
    PaletteIcon,
    RotateCcwIcon,
    SaveIcon,
    SunIcon,
    Trash2Icon,
} from 'lucide-react';
import {toast} from 'sonner';
import {RequireAuth} from '@/lib/auth/RequireAuth';
import {AppRole, useAuth} from '@/lib/auth/AuthProvider';
import {isApiError} from '@/lib/api/http';
import {useConfig} from '@/lib/config/ConfigProvider';
import {Button} from '@/components/ui/button';
import {FormRow, Input} from '@/components/ui/input';
import {Slider, Switch} from '@/components/ui/controls';
import {
    Alert,
    Badge,
    EmptyState,
    Skeleton,
    Tabs,
    TabsContent,
    TabsList,
    TabsTrigger,
} from '@/components/ui/misc';
import {useConfirm} from '@/components/ui/confirm';
import {cn} from '@/lib/utils/cn';
import {deleteClientTheme, getClientTheme, putClientTheme} from './api';
import {
    baseThemeColors,
    ClientTheme,
    ClientThemeFont,
    isHexColor,
    resolveThemeColors,
    THEME_LIMITS,
    ThemeColorToken,
} from './customTheme';
import {ThemeFontFields} from './ThemeFontFields';
import {CUSTOM_THEME_ID, ThemeMode} from './presets';
import {ThemeSwatch} from './ThemeMenu';
import {useThemeStore} from './themeStore';

type Palette = Record<ThemeColorToken, string>;

/** The form state: complete palettes, the dark one kept even when disabled */
type Draft = Omit<ClientTheme, 'colors' | 'dark' | 'fonts'> & {
    colors: Palette;
    darkEnabled: boolean;
    dark: Palette;
    fonts: ClientThemeFont[];
};

const colorGroups: {
    key: string;
    label: [string, string];
    tokens: ThemeColorToken[];
}[] = [
    {
        key: 'surfaces',
        label: ['theme.group.surfaces', 'Surfaces'],
        tokens: ['background', 'card', 'popover', 'sidebar', 'media-bg'],
    },
    {
        key: 'text',
        label: ['theme.group.text', 'Text'],
        tokens: [
            'foreground',
            'card-foreground',
            'popover-foreground',
            'sidebar-foreground',
            'muted-foreground',
        ],
    },
    {
        key: 'brand',
        label: ['theme.group.brand', 'Brand & accents'],
        tokens: [
            'primary',
            'primary-foreground',
            'secondary',
            'secondary-foreground',
            'accent',
            'accent-foreground',
            'muted',
        ],
    },
    {
        key: 'states',
        label: ['theme.group.states', 'States'],
        tokens: [
            'destructive',
            'destructive-foreground',
            'warning',
            'warning-foreground',
            'success',
            'success-foreground',
        ],
    },
    {
        key: 'borders',
        label: ['theme.group.borders', 'Borders & focus'],
        tokens: ['border', 'input', 'ring'],
    },
];

const tokenLabels: Record<ThemeColorToken, string> = {
    'background': 'Background',
    'foreground': 'Text',
    'card': 'Card',
    'card-foreground': 'Card text',
    'popover': 'Popover / menu',
    'popover-foreground': 'Popover text',
    'primary': 'Primary',
    'primary-foreground': 'Text on primary',
    'secondary': 'Secondary',
    'secondary-foreground': 'Text on secondary',
    'muted': 'Muted surface',
    'muted-foreground': 'Muted text',
    'accent': 'Accent (hover)',
    'accent-foreground': 'Text on accent',
    'destructive': 'Destructive',
    'destructive-foreground': 'Text on destructive',
    'warning': 'Warning',
    'warning-foreground': 'Text on warning',
    'success': 'Success',
    'success-foreground': 'Text on success',
    'border': 'Border',
    'input': 'Input border',
    'ring': 'Focus ring',
    'sidebar': 'Side panel',
    'sidebar-foreground': 'Side panel text',
    'media-bg': 'Media background',
};

function newDraft(name: string): Draft {
    return {
        name,
        default: false,
        colors: {...baseThemeColors.light},
        darkEnabled: false,
        dark: {...baseThemeColors.dark},
        radius: THEME_LIMITS.radius.default,
        fontSize: THEME_LIMITS.fontSize.default,
        fontFamily: '',
        letterSpacing: THEME_LIMITS.letterSpacing.default,
        fonts: [],
    };
}

function toDraft(theme: ClientTheme): Draft {
    return {
        ...theme,
        colors: resolveThemeColors(theme, 'light'),
        darkEnabled: !!theme.dark,
        dark: resolveThemeColors(theme, 'dark'),
        radius: theme.radius ?? THEME_LIMITS.radius.default,
        fontSize: theme.fontSize ?? THEME_LIMITS.fontSize.default,
        fontFamily: theme.fontFamily ?? '',
        letterSpacing:
            theme.letterSpacing ?? THEME_LIMITS.letterSpacing.default,
        fonts: theme.fonts ?? [],
    };
}

/** What is sent to the API (and previewed): the dark palette only when enabled */
function toTheme(draft: Draft): ClientTheme {
    return {
        name: draft.name.trim(),
        default: draft.default,
        colors: draft.colors,
        dark: draft.darkEnabled ? draft.dark : undefined,
        radius: draft.radius,
        fontSize: draft.fontSize,
        fontFamily: draft.fontFamily?.trim() || undefined,
        letterSpacing: draft.letterSpacing || undefined,
        fonts: draft.fonts.length > 0 ? draft.fonts : undefined,
    };
}

export function ThemeEditorScreen() {
    const {t} = useTranslation();
    const {hasRole} = useAuth();
    const confirm = useConfirm();
    const queryClient = useQueryClient();
    const compiled = useConfig().theme;
    const setPreview = useThemeStore(s => s.setPreview);
    const selectedTheme = useThemeStore(s => s.theme);
    const selectTheme = useThemeStore(s => s.setTheme);
    const {resolvedTheme, setTheme: setAppearance} = useTheme();
    const [draft, setDraft] = useState<Draft>();
    const [saving, setSaving] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const isAdmin = hasRole(AppRole.DataboxAdmin) || hasRole(AppRole.Admin);
    const query = useQuery({
        queryKey: ['client-theme'],
        queryFn: getClientTheme,
        enabled: isAdmin,
        staleTime: 0,
    });
    // undefined while loading, null when none is defined
    const saved = query.data;
    const appearance: ThemeMode = resolvedTheme === 'dark' ? 'dark' : 'light';

    useEffect(() => {
        if (draft === undefined && saved !== undefined) {
            setDraft(
                saved
                    ? toDraft(saved)
                    : newDraft(
                          t('theme.editor.default_name', 'My organisation')
                      )
            );
        }
    }, [draft, saved, t]);

    // Live preview of the draft on the whole page, in the current appearance
    useEffect(() => {
        if (isAdmin) {
            setPreview(draft ? toTheme(draft) : null);
        }
    }, [draft, isAdmin, setPreview]);
    useEffect(() => () => setPreview(null), [setPreview]);

    const update = (patch: Partial<Draft>) =>
        setDraft(d => (d ? {...d, ...patch} : d));

    const setColor = (mode: ThemeMode, token: ThemeColorToken, value: string) =>
        setDraft(d => {
            if (!d) {
                return d;
            }
            const key = mode === 'dark' ? 'dark' : 'colors';

            return {...d, [key]: {...d[key], [token]: value.toLowerCase()}};
        });

    const resetPalette = (mode: ThemeMode) =>
        setDraft(d =>
            d
                ? {
                      ...d,
                      [mode === 'dark' ? 'dark' : 'colors']: {
                          ...baseThemeColors[mode],
                      },
                  }
                : d
        );

    const onSave = async () => {
        if (!draft) {
            return;
        }
        setSaving(true);
        setErrors({});
        try {
            const result = await putClientTheme(toTheme(draft));
            queryClient.setQueryData(['client-theme'], result);
            toast.success(
                t(
                    'theme.editor.saved',
                    'Theme saved. It reaches the users once the stack configuration has been pushed (about a minute).'
                )
            );
        } catch (e) {
            if (isApiError(e, 422)) {
                setErrors(
                    Object.fromEntries(
                        e.violations.map(v => [v.propertyPath, v.message])
                    )
                );
            } else {
                toast.error(
                    t('theme.editor.save_failed', 'Unable to save the theme')
                );
            }
        } finally {
            setSaving(false);
        }
    };

    const onDelete = async () => {
        const ok = await confirm({
            title: t('theme.editor.delete', 'Remove the organisation theme'),
            description: t(
                'theme.editor.delete_confirm',
                'Users who selected it will go back to the default theme.'
            ),
            confirmLabel: t('common.delete', 'Delete'),
            destructive: true,
            onConfirm: () => deleteClientTheme(),
        });
        if (ok) {
            queryClient.setQueryData(['client-theme'], null);
            toast.success(t('theme.editor.deleted', 'Theme removed'));
            setDraft(
                newDraft(t('theme.editor.default_name', 'My organisation'))
            );
        }
    };

    return (
        <RequireAuth>
            {!isAdmin ? (
                <EmptyState
                    className="h-full"
                    title={t(
                        'common.forbidden',
                        'You are not allowed to access this page'
                    )}
                />
            ) : (
                <div
                    className="mx-auto w-full max-w-5xl space-y-4 overflow-y-auto p-4"
                    data-testid="theme-editor"
                >
                    <div className="flex flex-wrap items-center gap-2">
                        <PaletteIcon className="size-5 text-muted-foreground" />
                        <h1 className="flex-1 text-lg font-semibold">
                            {t('theme.editor.title', 'Organisation theme')}
                        </h1>
                        {saved ? (
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={onDelete}
                                data-testid="theme-delete"
                            >
                                <Trash2Icon /> {t('common.delete', 'Delete')}
                            </Button>
                        ) : null}
                        <Button
                            size="sm"
                            onClick={onSave}
                            disabled={!draft || saving}
                            data-testid="theme-save"
                        >
                            <SaveIcon /> {t('common.save', 'Save')}
                        </Button>
                    </div>
                    <p className="text-sm text-muted-foreground">
                        {t(
                            'theme.editor.intro',
                            'Define a theme with your own palette and style properties. It is stored in the stack configuration and offered to every user in the theme menu, next to the light / dark appearance they choose; it can also be applied by default to those who never picked one.'
                        )}
                    </p>
                    {query.isError ? (
                        <Alert variant="destructive">
                            {t(
                                'theme.editor.load_failed',
                                'Unable to load the organisation theme'
                            )}
                        </Alert>
                    ) : null}

                    {!draft ? (
                        <div className="space-y-3">
                            <Skeleton className="h-10" />
                            <Skeleton className="h-40" />
                        </div>
                    ) : (
                        <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_300px]">
                            <div className="min-w-0 space-y-6">
                                {saved === null ? (
                                    <Alert variant="info">
                                        {t(
                                            'theme.editor.no_theme_yet',
                                            'No organisation theme is defined yet: this form starts from the default palette.'
                                        )}
                                    </Alert>
                                ) : null}
                                {errors[''] ? (
                                    <Alert variant="destructive">
                                        {errors['']}
                                    </Alert>
                                ) : null}

                                <section className="space-y-4 rounded-md border bg-card p-4">
                                    <FormRow
                                        label={t('common.name', 'Name')}
                                        htmlFor="theme-name"
                                        error={errors.name}
                                        help={t(
                                            'theme.editor.name_help',
                                            'Shown in the theme menu.'
                                        )}
                                    >
                                        <Input
                                            id="theme-name"
                                            data-testid="theme-name"
                                            value={draft.name}
                                            maxLength={
                                                THEME_LIMITS.nameMaxLength
                                            }
                                            aria-invalid={!!errors.name}
                                            onChange={e =>
                                                update({name: e.target.value})
                                            }
                                        />
                                    </FormRow>
                                    <FormRow inline error={errors.default}>
                                        <Switch
                                            id="theme-default"
                                            data-testid="theme-default"
                                            checked={draft.default}
                                            onCheckedChange={v =>
                                                update({default: v})
                                            }
                                        />
                                        <label
                                            htmlFor="theme-default"
                                            className="text-sm"
                                        >
                                            {t(
                                                'theme.editor.default',
                                                'Apply by default to users who never picked a theme'
                                            )}
                                        </label>
                                    </FormRow>
                                </section>

                                <section className="space-y-4 rounded-md border bg-card p-4">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <h2 className="flex-1 text-sm font-semibold">
                                            {t(
                                                'theme.editor.palette',
                                                'Palette'
                                            )}
                                        </h2>
                                        <span className="text-xs text-muted-foreground">
                                            {t(
                                                'theme.editor.preview_as',
                                                'Preview as'
                                            )}
                                        </span>
                                        <Button
                                            variant={
                                                appearance === 'light'
                                                    ? 'secondary'
                                                    : 'ghost'
                                            }
                                            size="sm"
                                            data-testid="theme-preview-light"
                                            onClick={() =>
                                                setAppearance('light')
                                            }
                                        >
                                            <SunIcon />{' '}
                                            {t('settings.theme_light', 'Light')}
                                        </Button>
                                        <Button
                                            variant={
                                                appearance === 'dark'
                                                    ? 'secondary'
                                                    : 'ghost'
                                            }
                                            size="sm"
                                            data-testid="theme-preview-dark"
                                            onClick={() =>
                                                setAppearance('dark')
                                            }
                                        >
                                            <MoonIcon />{' '}
                                            {t('settings.theme_dark', 'Dark')}
                                        </Button>
                                    </div>
                                    {errors.colors || errors.dark ? (
                                        <p className="text-xs text-destructive">
                                            {errors.colors ?? errors.dark}
                                        </p>
                                    ) : null}
                                    <Tabs defaultValue="light">
                                        <TabsList>
                                            <TabsTrigger
                                                value="light"
                                                data-testid="theme-tab-light"
                                            >
                                                <SunIcon />{' '}
                                                {t(
                                                    'theme.editor.light_palette',
                                                    'Light palette'
                                                )}
                                            </TabsTrigger>
                                            <TabsTrigger
                                                value="dark"
                                                data-testid="theme-tab-dark"
                                            >
                                                <MoonIcon />{' '}
                                                {t(
                                                    'theme.editor.dark_palette',
                                                    'Dark alternative'
                                                )}
                                            </TabsTrigger>
                                        </TabsList>
                                        <TabsContent value="light">
                                            <PaletteEditor
                                                mode="light"
                                                palette={draft.colors}
                                                errors={errors}
                                                onChange={(token, v) =>
                                                    setColor('light', token, v)
                                                }
                                                onReset={() =>
                                                    resetPalette('light')
                                                }
                                            />
                                        </TabsContent>
                                        <TabsContent value="dark">
                                            <FormRow inline>
                                                <Switch
                                                    id="theme-dark-enabled"
                                                    data-testid="theme-dark-enabled"
                                                    checked={draft.darkEnabled}
                                                    onCheckedChange={v =>
                                                        update({
                                                            darkEnabled: v,
                                                        })
                                                    }
                                                />
                                                <label
                                                    htmlFor="theme-dark-enabled"
                                                    className="text-sm"
                                                >
                                                    {t(
                                                        'theme.editor.dark_enabled',
                                                        'Provide a dark alternative'
                                                    )}
                                                </label>
                                            </FormRow>
                                            <p className="mb-3 text-xs text-muted-foreground">
                                                {t(
                                                    'theme.editor.dark_help',
                                                    'Used when the user chooses the dark appearance. Without it, the default dark palette is used with the style of this theme.'
                                                )}
                                            </p>
                                            {draft.darkEnabled ? (
                                                <PaletteEditor
                                                    mode="dark"
                                                    palette={draft.dark}
                                                    errors={errors}
                                                    onChange={(token, v) =>
                                                        setColor(
                                                            'dark',
                                                            token,
                                                            v
                                                        )
                                                    }
                                                    onReset={() =>
                                                        resetPalette('dark')
                                                    }
                                                />
                                            ) : null}
                                        </TabsContent>
                                    </Tabs>
                                </section>

                                <section className="space-y-4 rounded-md border bg-card p-4">
                                    <h2 className="text-sm font-semibold">
                                        {t('theme.editor.style', 'Style')}
                                    </h2>
                                    <FormRow
                                        label={`${t('theme.editor.radius', 'Corner radius')} — ${draft.radius}rem`}
                                        error={errors.radius}
                                    >
                                        <Slider
                                            data-testid="theme-radius"
                                            value={[
                                                draft.radius ??
                                                    THEME_LIMITS.radius.default,
                                            ]}
                                            min={THEME_LIMITS.radius.min}
                                            max={THEME_LIMITS.radius.max}
                                            step={THEME_LIMITS.radius.step}
                                            onValueChange={([v]) =>
                                                update({radius: v})
                                            }
                                        />
                                    </FormRow>
                                    <FormRow
                                        label={`${t('theme.editor.font_size', 'Base font size')} — ${draft.fontSize}px`}
                                        error={errors.fontSize}
                                    >
                                        <Slider
                                            data-testid="theme-font-size"
                                            value={[
                                                draft.fontSize ??
                                                    THEME_LIMITS.fontSize
                                                        .default,
                                            ]}
                                            min={THEME_LIMITS.fontSize.min}
                                            max={THEME_LIMITS.fontSize.max}
                                            step={1}
                                            onValueChange={([v]) =>
                                                update({fontSize: v})
                                            }
                                        />
                                    </FormRow>
                                    <FormRow
                                        label={`${t('theme.editor.letter_spacing', 'Letter spacing')} — ${draft.letterSpacing ?? 0}em`}
                                        error={errors.letterSpacing}
                                    >
                                        <Slider
                                            data-testid="theme-letter-spacing"
                                            value={[
                                                draft.letterSpacing ??
                                                    THEME_LIMITS.letterSpacing
                                                        .default,
                                            ]}
                                            min={THEME_LIMITS.letterSpacing.min}
                                            max={THEME_LIMITS.letterSpacing.max}
                                            step={
                                                THEME_LIMITS.letterSpacing.step
                                            }
                                            onValueChange={([v]) =>
                                                update({
                                                    letterSpacing:
                                                        Math.round(v * 1000) /
                                                        1000,
                                                })
                                            }
                                        />
                                    </FormRow>
                                    <ThemeFontFields
                                        fontFamily={draft.fontFamily ?? ''}
                                        fonts={draft.fonts}
                                        error={
                                            errors.fontFamily ?? errors.fonts
                                        }
                                        onChange={update}
                                    />
                                </section>
                            </div>

                            <aside className="space-y-3 lg:sticky lg:top-0 lg:self-start">
                                <h2 className="text-sm font-semibold">
                                    {t('theme.editor.sample', 'Sample')}
                                </h2>
                                <SampleCard name={draft.name} />
                                {compiled &&
                                selectedTheme !== CUSTOM_THEME_ID ? (
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        className="w-full"
                                        onClick={() =>
                                            selectTheme(CUSTOM_THEME_ID)
                                        }
                                        data-testid="theme-use"
                                    >
                                        <CheckIcon />{' '}
                                        {t(
                                            'theme.editor.use_theme',
                                            'Use this theme myself'
                                        )}
                                    </Button>
                                ) : null}
                            </aside>
                        </div>
                    )}
                </div>
            )}
        </RequireAuth>
    );
}

function PaletteEditor({
    mode,
    palette,
    errors,
    onChange,
    onReset,
}: {
    mode: ThemeMode;
    palette: Palette;
    errors: Record<string, string>;
    onChange: (token: ThemeColorToken, value: string) => void;
    onReset: () => void;
}) {
    const {t} = useTranslation();
    const prefix = mode === 'dark' ? 'dark' : 'colors';

    return (
        <div className="space-y-4" data-testid={`theme-palette-${mode}`}>
            <div className="flex justify-end">
                <Button variant="ghost" size="sm" onClick={onReset}>
                    <RotateCcwIcon />{' '}
                    {t('theme.editor.reset_palette', 'Reset to defaults')}
                </Button>
            </div>
            {colorGroups.map(group => (
                <div key={group.key}>
                    <h3 className="mb-2 text-xs font-medium tracking-wide text-muted-foreground uppercase">
                        {t(group.label[0], group.label[1])}
                    </h3>
                    <div className="grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
                        {group.tokens.map(token => (
                            <ColorField
                                key={token}
                                token={token}
                                label={t(
                                    `theme.token.${token}`,
                                    tokenLabels[token]
                                )}
                                value={palette[token]}
                                defaultValue={baseThemeColors[mode][token]}
                                error={errors[`${prefix}.${token}`]}
                                onChange={v => onChange(token, v)}
                            />
                        ))}
                    </div>
                </div>
            ))}
        </div>
    );
}

function ColorField({
    token,
    label,
    value,
    defaultValue,
    error,
    onChange,
}: {
    token: ThemeColorToken;
    label: string;
    value: string;
    defaultValue: string;
    error?: string;
    onChange: (value: string) => void;
}) {
    const [text, setText] = useState(value);
    useEffect(() => setText(value), [value]);
    const invalid = !isHexColor(text);
    const customized = value !== defaultValue;

    return (
        <div
            className={cn(
                'flex items-center gap-2 rounded-md border p-2',
                (error || invalid) && 'border-destructive'
            )}
            data-testid={`theme-color-${token}`}
        >
            <input
                type="color"
                aria-label={label}
                value={isHexColor(value) ? value : '#000000'}
                onChange={e => onChange(e.target.value)}
                className="size-8 shrink-0 cursor-pointer rounded border-0 bg-transparent p-0"
            />
            <div className="min-w-0 flex-1">
                <div className="flex items-center gap-1 text-sm">
                    <span className="truncate">{label}</span>
                    {customized ? (
                        <span
                            className="size-1.5 shrink-0 rounded-full bg-primary"
                            title="customized"
                        />
                    ) : null}
                </div>
                <input
                    value={text}
                    spellCheck={false}
                    aria-invalid={invalid || !!error}
                    className="w-full bg-transparent font-mono text-xs text-muted-foreground outline-none"
                    onChange={e => {
                        const v = e.target.value;
                        setText(v);
                        if (isHexColor(v)) {
                            onChange(v);
                        }
                    }}
                    onBlur={() => setText(value)}
                />
                {error ? (
                    <p className="text-xs text-destructive">{error}</p>
                ) : null}
            </div>
        </div>
    );
}

function SampleCard({name}: {name: string}) {
    const {t} = useTranslation();

    return (
        <div className="space-y-3 rounded-lg border bg-card p-4 text-card-foreground shadow-sm">
            <div className="flex items-center gap-2">
                <span className="flex size-7 items-center justify-center rounded-md bg-primary text-primary-foreground">
                    <PaletteIcon className="size-4" />
                </span>
                <span className="truncate font-semibold">{name || '—'}</span>
            </div>
            <p className="text-sm text-muted-foreground">
                {t(
                    'theme.editor.sample_text',
                    'The quick brown fox jumps over the lazy dog.'
                )}
            </p>
            <div className="flex flex-wrap gap-2">
                <Button size="sm">{t('common.save', 'Save')}</Button>
                <Button size="sm" variant="secondary">
                    {t('common.cancel', 'Cancel')}
                </Button>
                <Button size="sm" variant="outline">
                    {t('common.back', 'Back')}
                </Button>
                <Button size="sm" variant="destructive">
                    {t('common.delete', 'Delete')}
                </Button>
            </div>
            <div className="flex flex-wrap gap-1.5">
                <Badge>Tag</Badge>
                <Badge variant="secondary">Tag</Badge>
                <Badge variant="warning">Tag</Badge>
                <Badge variant="success">Tag</Badge>
                <Badge variant="muted">Tag</Badge>
            </div>
            <Input placeholder={t('search.submit', 'Search')} readOnly />
            <div className="rounded-md bg-sidebar p-2 text-xs text-sidebar-foreground">
                <div className="rounded-sm bg-accent px-2 py-1 text-accent-foreground">
                    Collection
                </div>
                <div className="px-2 py-1">Collection</div>
            </div>
            <div className="checkerboard flex h-16 items-center justify-center rounded-md text-xs text-muted-foreground">
                <ThemeSwatch
                    background="var(--background)"
                    primary="var(--primary)"
                    className="size-8 rounded-full border border-foreground/25"
                />
            </div>
        </div>
    );
}
