'use client';

import {useEffect, useRef, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {useTheme} from 'next-themes';
import {SmileIcon} from 'lucide-react';
import {Button} from '@/components/ui/button';
import {Spinner} from '@/components/ui/loader';
import {cn} from '@/lib/utils/cn';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
    Tooltip,
} from '@/components/ui/overlays';

/** emoji-mart translations bundled with the app (never fetched from its CDN) */
const i18nLoaders: Record<string, () => Promise<{default: unknown}>> = {
    de: () => import('@emoji-mart/data/i18n/de.json'),
    es: () => import('@emoji-mart/data/i18n/es.json'),
    fr: () => import('@emoji-mart/data/i18n/fr.json'),
};

/**
 * Emoji button opening the emoji-mart picker. The picker and its data
 * (~400 kB) are only loaded when the popover opens.
 */
export function EmojiPicker({
    onSelect,
    disabled,
}: {
    onSelect: (emoji: string) => void;
    disabled?: boolean;
}) {
    const {t} = useTranslation();
    const [open, setOpen] = useState(false);

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <Tooltip content={t('discussion.format.emoji', 'Emoji')}>
                <PopoverTrigger asChild>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon-xs"
                        disabled={disabled}
                        aria-label={t('discussion.format.emoji', 'Emoji')}
                        onMouseDown={e => e.preventDefault()}
                    >
                        <SmileIcon />
                    </Button>
                </PopoverTrigger>
            </Tooltip>
            <PopoverContent
                align="start"
                className="w-auto border-0 bg-transparent p-0 shadow-none"
                // The composer takes the focus back (see `onSelect`)
                onCloseAutoFocus={e => e.preventDefault()}
            >
                <EmojiMartPicker
                    onSelect={emoji => {
                        setOpen(false);
                        onSelect(emoji);
                    }}
                />
            </PopoverContent>
        </Popover>
    );
}

function EmojiMartPicker({onSelect}: {onSelect: (emoji: string) => void}) {
    const {i18n} = useTranslation();
    const {resolvedTheme} = useTheme();
    const containerRef = useRef<HTMLDivElement>(null);
    const onSelectRef = useRef(onSelect);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        onSelectRef.current = onSelect;
    }, [onSelect]);

    const theme = resolvedTheme === 'dark' ? 'dark' : 'light';
    const lang = (i18n.language ?? 'en').split(/[-_]/)[0];

    useEffect(() => {
        let cancelled = false;
        let picker: HTMLElement | undefined;

        void Promise.all([
            import('emoji-mart'),
            import('@emoji-mart/data') as unknown as Promise<{
                default: unknown;
            }>,
            i18nLoaders[lang]?.(),
        ]).then(([{Picker}, {default: data}, translations]) => {
            if (cancelled || !containerRef.current) {
                return;
            }
            picker = new Picker({
                data,
                i18n: translations?.default,
                locale: translations ? lang : 'en',
                theme,
                autoFocus: true,
                previewPosition: 'none',
                skinTonePosition: 'search',
                onEmojiSelect: (e: {native: string}) =>
                    onSelectRef.current(e.native),
            }) as unknown as HTMLElement;
            containerRef.current.replaceChildren(picker);
            setLoading(false);
        });

        return () => {
            cancelled = true;
            picker?.remove();
        };
    }, [theme, lang]);

    return (
        <div
            className={cn(
                'relative',
                // emoji-mart draws its own frame once loaded
                loading &&
                    'min-h-[435px] min-w-[352px] rounded-md border bg-popover shadow-md'
            )}
        >
            {loading ? (
                <div className="absolute inset-0 flex items-center justify-center">
                    <Spinner />
                </div>
            ) : null}
            {/* Filled by emoji-mart, out of React's hands */}
            <div ref={containerRef} />
        </div>
    );
}
