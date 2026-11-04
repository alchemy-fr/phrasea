'use client';

import {useTranslation} from 'react-i18next';
import {SavedSearchPrivacy} from '@/types/api';
import {RadioGroup, RadioGroupItem} from '@alchemy/next-ui/ui/controls';
import {Label} from '@alchemy/next-ui/ui/input';
import {cn} from '@alchemy/next-ui/utils/cn';

export function SavedSearchPrivacyField({
    value,
    onChange,
    canPublish = true,
    initialValue = SavedSearchPrivacy.Secret,
}: {
    value: SavedSearchPrivacy;
    onChange: (value: SavedSearchPrivacy) => void;
    /** Without it, only Secret and the current privacy can be chosen */
    canPublish?: boolean;
    /** Privacy of the saved search, which can be kept without publishing */
    initialValue?: SavedSearchPrivacy;
}) {
    const {t} = useTranslation();
    const options = [
        {
            value: SavedSearchPrivacy.Secret,
            label: t('saved_search.privacy.secret', 'Secret'),
            help: t(
                'saved_search.privacy.secret_help',
                'Only you and explicitly allowed users'
            ),
        },
        {
            value: SavedSearchPrivacy.Private,
            label: t('saved_search.privacy.private', 'Private'),
            help: t(
                'saved_search.privacy.private_help',
                'Accessible by link, not listed'
            ),
        },
        {
            value: SavedSearchPrivacy.Public,
            label: t('saved_search.privacy.public', 'Public'),
            help: t('saved_search.privacy.public_help', 'Listed for everyone'),
        },
    ];

    return (
        <div className="mb-4">
            <Label className="mb-2">{t('common.privacy', 'Privacy')}</Label>
            <RadioGroup
                value={String(value)}
                onValueChange={v => onChange(Number(v) as SavedSearchPrivacy)}
                className="space-y-2"
            >
                {options.map(o => {
                    const disabled =
                        !canPublish &&
                        o.value !== SavedSearchPrivacy.Secret &&
                        o.value !== initialValue;

                    return (
                        <label
                            key={o.value}
                            className={cn(
                                'flex items-start gap-2 text-sm',
                                disabled
                                    ? 'cursor-not-allowed opacity-60'
                                    : 'cursor-pointer'
                            )}
                        >
                            <RadioGroupItem
                                value={String(o.value)}
                                className="mt-0.5"
                                disabled={disabled}
                            />
                            <span>
                                <span className="font-medium">{o.label}</span>
                                <span className="block text-xs text-muted-foreground">
                                    {o.help}
                                </span>
                            </span>
                        </label>
                    );
                })}
            </RadioGroup>
            {!canPublish ? (
                <p className="mt-2 text-xs text-muted-foreground">
                    {t(
                        'saved_search.privacy.cannot_publish',
                        'You are not allowed to publish searches.'
                    )}
                </p>
            ) : null}
        </div>
    );
}
