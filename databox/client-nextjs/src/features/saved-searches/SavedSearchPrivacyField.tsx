'use client';

import {useTranslation} from 'react-i18next';
import {SavedSearchPrivacy} from '@/types/api';
import {RadioGroup, RadioGroupItem} from '@/components/ui/controls';
import {Label} from '@/components/ui/input';

export function SavedSearchPrivacyField({
    value,
    onChange,
}: {
    value: SavedSearchPrivacy;
    onChange: (value: SavedSearchPrivacy) => void;
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
                {options.map(o => (
                    <label
                        key={o.value}
                        className="flex cursor-pointer items-start gap-2 text-sm"
                    >
                        <RadioGroupItem
                            value={String(o.value)}
                            className="mt-0.5"
                        />
                        <span>
                            <span className="font-medium">{o.label}</span>
                            <span className="block text-xs text-muted-foreground">
                                {o.help}
                            </span>
                        </span>
                    </label>
                ))}
            </RadioGroup>
        </div>
    );
}
