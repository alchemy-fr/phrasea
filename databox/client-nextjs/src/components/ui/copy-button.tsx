'use client';

import {useState} from 'react';
import {CheckIcon, CopyIcon} from 'lucide-react';
import {useTranslation} from 'react-i18next';
import {Button, type ButtonProps} from './button';
import {Tooltip} from './overlays';
import {copyToClipboard} from '@/lib/utils/misc';
import {cn} from '@/lib/utils/cn';

export function CopyButton({
    value,
    className,
    size = 'icon-xs',
    variant = 'ghost',
    label,
    ...props
}: Omit<ButtonProps, 'value' | 'onClick'> & {value: string; label?: string}) {
    const {t} = useTranslation();
    const [copied, setCopied] = useState(false);

    return (
        <Tooltip
            content={
                copied
                    ? t('common.copied', 'Copied!')
                    : (label ?? t('common.copy', 'Copy'))
            }
        >
            <Button
                type="button"
                variant={variant}
                size={size}
                className={cn('text-muted-foreground', className)}
                onClick={async e => {
                    e.stopPropagation();
                    await copyToClipboard(value);
                    setCopied(true);
                    setTimeout(() => setCopied(false), 1500);
                }}
                {...props}
            >
                {copied ? <CheckIcon className="text-success" /> : <CopyIcon />}
            </Button>
        </Tooltip>
    );
}

export function CopiableText({
    value,
    className,
}: {
    value: string;
    className?: string;
}) {
    return (
        <span
            className={cn(
                'group/copy inline-flex max-w-full items-center gap-1',
                className
            )}
        >
            <span className="truncate font-mono text-xs">{value}</span>
            <CopyButton
                value={value}
                className="opacity-0 group-hover/copy:opacity-100"
            />
        </span>
    );
}
