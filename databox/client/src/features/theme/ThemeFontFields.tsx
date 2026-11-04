'use client';

import {useRef, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {Trash2Icon, TypeIcon, UploadIcon} from 'lucide-react';
import {toast} from 'sonner';
import {Button} from '@/components/ui/button';
import {FormRow, Input, Label} from '@/components/ui/input';
import {SimpleSelect} from '@/components/ui/select';
import {
    ClientThemeFont,
    FONT_FAMILY_NAME_RE,
    THEME_LIMITS,
} from './customTheme';
import {themeFonts} from './fonts';

/**
 * The value of the select standing for "a CSS font list of my own". Starts
 * with a character no family name may contain: it can never be one.
 */
const CUSTOM_VALUE = '@custom';
const DEFAULT_VALUE = '';

function base64(buffer: ArrayBuffer): string {
    const bytes = new Uint8Array(buffer);
    let binary = '';
    // Chunked: String.fromCharCode blows the stack on a whole font
    for (let i = 0; i < bytes.length; i += 0x8000) {
        binary += String.fromCharCode(...bytes.subarray(i, i + 0x8000));
    }

    return btoa(binary);
}

/** A family name taken from the file name, e.g. `Acme-Sans-Bold.woff2` */
function familyFromFileName(name: string): string {
    const base = name
        .replace(/\.[^.]+$/, '')
        .replace(/[_-]+/g, ' ')
        .replace(/[^a-zA-Z0-9 ]/g, '')
        .trim()
        .slice(0, THEME_LIMITS.fonts.familyMaxLength);

    return FONT_FAMILY_NAME_RE.test(base) ? base : 'Custom font';
}

/**
 * The typography of the organisation theme: the family the interface is set
 * in — one of the built-in Google fonts, one of the fonts uploaded here, or
 * a plain CSS list of fonts installed on the users' devices — and the font
 * files themselves, kept in the configuration of the stack as data URIs.
 */
export function ThemeFontFields({
    fontFamily,
    fonts,
    error,
    onChange,
}: {
    fontFamily: string;
    fonts: ClientThemeFont[];
    error?: string;
    onChange: (patch: {fontFamily?: string; fonts?: ClientThemeFont[]}) => void;
}) {
    const {t} = useTranslation();
    const fileInput = useRef<HTMLInputElement>(null);
    const uploadedFamilies = [...new Set(fonts.map(f => f.family))];
    const known =
        !fontFamily ||
        themeFonts.some(f => f.id === fontFamily) ||
        uploadedFamilies.includes(fontFamily);
    // Kept: "font list" with an empty value cannot be told from the default
    const [custom, setCustom] = useState(!known);
    const selected = custom ? CUSTOM_VALUE : (fontFamily ?? DEFAULT_VALUE);

    const addFile = async (file: File) => {
        const type = file.name.split('.').pop()?.toLowerCase() ?? '';
        if (!(THEME_LIMITS.fonts.types as readonly string[]).includes(type)) {
            toast.error(
                t(
                    'theme.editor.font_type_error',
                    'Only {{types}} files can be uploaded',
                    {types: THEME_LIMITS.fonts.types.join(', ')}
                )
            );

            return;
        }
        const src = `data:font/${type};base64,${base64(await file.arrayBuffer())}`;
        if (src.length > THEME_LIMITS.fonts.srcMaxLength) {
            toast.error(
                t(
                    'theme.editor.font_size_error',
                    'This file is too large: subset the font, or convert it to woff2.'
                )
            );

            return;
        }
        const font: ClientThemeFont = {
            family: familyFromFileName(file.name),
            src,
            weight: 'normal',
            style: 'normal',
        };
        onChange({
            fonts: [...fonts, font],
            // The first uploaded font is most likely the one wanted
            ...(fonts.length === 0 ? {fontFamily: font.family} : {}),
        });
        setCustom(false);
    };

    const updateFont = (index: number, patch: Partial<ClientThemeFont>) => {
        const next = fonts.map((f, i) => (i === index ? {...f, ...patch} : f));
        // Renaming the selected family keeps it selected
        const renamed = patch.family && fonts[index].family === fontFamily;
        onChange({
            fonts: next,
            ...(renamed ? {fontFamily: patch.family} : {}),
        });
    };

    const removeFont = (index: number) => {
        const removed = fonts[index];
        const next = fonts.filter((_, i) => i !== index);
        const orphaned =
            removed.family === fontFamily &&
            !next.some(f => f.family === removed.family);
        onChange({fonts: next, ...(orphaned ? {fontFamily: ''} : {})});
    };

    return (
        <>
            <FormRow
                label={t('theme.editor.font_family', 'Font family')}
                error={error}
                help={t(
                    'theme.editor.font_family_help2',
                    'The built-in fonts are served by the application itself. A font list of your own must be installed on the users’ devices — upload the file below otherwise.'
                )}
            >
                <SimpleSelect
                    value={selected}
                    onValueChange={value => {
                        if (value === CUSTOM_VALUE) {
                            setCustom(true);
                            onChange({fontFamily: ''});
                        } else {
                            setCustom(false);
                            onChange({fontFamily: value});
                        }
                    }}
                    options={[
                        {
                            value: DEFAULT_VALUE,
                            label: t(
                                'theme.editor.font_default',
                                'System font'
                            ),
                        },
                        ...uploadedFamilies.map(family => ({
                            value: family,
                            label: (
                                <span style={{fontFamily: `'${family}'`}}>
                                    {family}
                                </span>
                            ),
                        })),
                        ...themeFonts.map(font => ({
                            value: font.id,
                            label: (
                                <span
                                    style={{fontFamily: `var(${font.cssVar})`}}
                                >
                                    {font.label}
                                </span>
                            ),
                        })),
                        {
                            value: CUSTOM_VALUE,
                            label: t('theme.editor.font_custom', 'Font list…'),
                        },
                    ]}
                />
            </FormRow>
            {custom ? (
                <Input
                    id="theme-font-family"
                    data-testid="theme-font-family"
                    value={fontFamily}
                    maxLength={THEME_LIMITS.fontFamilyMaxLength}
                    placeholder="ui-sans-serif, system-ui, sans-serif"
                    aria-label={t('theme.editor.font_family', 'Font family')}
                    aria-invalid={!!error}
                    onChange={e => onChange({fontFamily: e.target.value})}
                />
            ) : null}

            <div className="space-y-2">
                <Label>{t('theme.editor.fonts', 'Uploaded fonts')}</Label>
                {fonts.map((font, index) => (
                    <div
                        key={index}
                        data-testid="theme-font"
                        className="flex flex-wrap items-center gap-2 rounded-md border p-2"
                    >
                        <TypeIcon className="size-4 shrink-0 text-muted-foreground" />
                        <Input
                            className="w-40"
                            value={font.family}
                            maxLength={THEME_LIMITS.fonts.familyMaxLength}
                            aria-label={t('common.name', 'Name')}
                            aria-invalid={
                                !FONT_FAMILY_NAME_RE.test(font.family)
                            }
                            onChange={e =>
                                updateFont(index, {family: e.target.value})
                            }
                        />
                        <SimpleSelect
                            className="w-28"
                            size="sm"
                            value={font.weight ?? 'normal'}
                            onValueChange={weight =>
                                updateFont(index, {weight})
                            }
                            options={THEME_LIMITS.fonts.weights.map(w => ({
                                value: w,
                                label: w,
                            }))}
                        />
                        <SimpleSelect
                            className="w-28"
                            size="sm"
                            value={font.style ?? 'normal'}
                            onValueChange={style =>
                                updateFont(index, {
                                    style: style as ClientThemeFont['style'],
                                })
                            }
                            options={THEME_LIMITS.fonts.styles.map(s => ({
                                value: s,
                                label: s,
                            }))}
                        />
                        <span className="text-xs text-muted-foreground tabular-nums">
                            {Math.round((font.src.length * 3) / 4 / 1024)} kB
                        </span>
                        <div className="flex-1" />
                        <Button
                            variant="ghost"
                            size="icon-sm"
                            className="text-destructive"
                            aria-label={t('common.delete', 'Delete')}
                            onClick={() => removeFont(index)}
                        >
                            <Trash2Icon />
                        </Button>
                    </div>
                ))}
                <input
                    ref={fileInput}
                    type="file"
                    hidden
                    accept={THEME_LIMITS.fonts.types
                        .map(type => `.${type}`)
                        .join(',')}
                    onChange={e => {
                        const file = e.target.files?.[0];
                        e.target.value = '';
                        if (file) {
                            void addFile(file);
                        }
                    }}
                />
                <Button
                    variant="outline"
                    size="sm"
                    data-testid="theme-font-upload"
                    disabled={fonts.length >= THEME_LIMITS.fonts.max}
                    onClick={() => fileInput.current?.click()}
                >
                    <UploadIcon />{' '}
                    {t('theme.editor.font_upload', 'Upload a font file')}
                </Button>
                <p className="text-xs text-muted-foreground">
                    {t(
                        'theme.editor.fonts_help',
                        'Up to {{count}} files (woff2 recommended), stored in the configuration of the stack and served to every user. One file per weight and style of the same family name.',
                        {count: THEME_LIMITS.fonts.max}
                    )}
                </p>
            </div>
        </>
    );
}
