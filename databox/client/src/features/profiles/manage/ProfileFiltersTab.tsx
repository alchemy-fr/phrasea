'use client';

import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import type {TFunction} from 'i18next';
import {
    AttributeType,
    FilterWidget,
    ProfileItemSection,
    type AttributeDefinitionOrBuiltIn,
    type ProfileItem,
} from '@/types/api';
import {Badge} from '@/components/ui/misc';
import {Input} from '@/components/ui/input';
import {SimpleSelect} from '@/components/ui/select';
import {
    Checkbox,
    LabeledControl,
    RadioGroup,
    RadioGroupItem,
} from '@/components/ui/controls';
import {booleanChoices} from '@/features/search/pinned/booleanChoices';
import type {ProfileTabProps} from './ProfileManageRoute';
import {ProfileSectionEditor} from './ProfileAttributesTab';

const searchable = (d: AttributeDefinitionOrBuiltIn) =>
    !!d.builtIn || d.searchable;

function widgetLabels(t: TFunction): Record<FilterWidget, string> {
    return {
        [FilterWidget.Select]: t(
            'profile.filters.widget_select',
            'Dropdown menu'
        ),
        [FilterWidget.Radio]: t(
            'profile.filters.widget_radio',
            'Radio buttons'
        ),
        [FilterWidget.Inline]: t(
            'profile.filters.widget_inline',
            'Radio buttons in the search bar'
        ),
    };
}

/**
 * The search filters pinned by the profile: conditions on an attribute,
 * offered above the results with no value yet.
 */
export function ProfileFiltersTab({profile}: ProfileTabProps) {
    const {t} = useTranslation();

    return (
        <ProfileSectionEditor
            profile={profile}
            section={ProfileItemSection.Filters}
            accept={searchable}
            intro={t(
                'profile.filters.intro',
                'The search filters pinned above the results, in this order. Clicking a filter offers a value picker suited to the attribute type.'
            )}
            emptyText={t(
                'profile.filters.empty',
                'No search filter is pinned yet.'
            )}
            badges={item =>
                item.widget && item.widget !== FilterWidget.Select ? (
                    <Badge variant="muted">
                        {widgetLabels(t)[item.widget]}
                    </Badge>
                ) : null
            }
            options={(item, type, onChange) =>
                type === AttributeType.Boolean ? (
                    <BooleanFilterOptions item={item} onChange={onChange} />
                ) : null
            }
        />
    );
}

function BooleanFilterOptions({
    item,
    onChange,
}: {
    item: ProfileItem;
    onChange: (data: Partial<ProfileItem>) => void;
}) {
    const {t} = useTranslation();
    const labels = widgetLabels(t);

    return (
        <div className="space-y-3 border-t bg-muted/30 px-3 py-2.5 text-xs text-muted-foreground">
            <label className="flex items-center gap-2">
                <span className="w-28 shrink-0">
                    {t('profile.filters.picker', 'Value picker')}
                </span>
                <SimpleSelect
                    className="h-8 w-64"
                    value={item.widget || FilterWidget.Select}
                    onValueChange={v => onChange({widget: v})}
                    options={Object.values(FilterWidget).map(w => ({
                        value: w,
                        label: labels[w],
                    }))}
                />
            </label>
            <ValueLabelInput
                label={t('profile.filters.true_label', 'Label of "true"')}
                placeholder={t('common.yes', 'Yes')}
                value={item.trueLabel ?? ''}
                onSave={trueLabel => onChange({trueLabel})}
            />
            <ValueLabelInput
                label={t('profile.filters.false_label', 'Label of "false"')}
                placeholder={t('common.no', 'No')}
                value={item.falseLabel ?? ''}
                onSave={falseLabel => onChange({falseLabel})}
            />
            <LabeledControl
                label={t('profile.filters.reversed', 'Offer "false" first')}
            >
                <Checkbox
                    checked={!!item.reversed}
                    onCheckedChange={v => onChange({reversed: v === true})}
                />
            </LabeledControl>
            <div className="flex items-center gap-2">
                <span className="w-28 shrink-0">
                    {t('profile.filters.preview', 'Preview')}
                </span>
                <RadioGroup className="flex items-center gap-3 text-sm text-foreground">
                    {booleanChoices(item, t).map(c => (
                        <span
                            key={String(c.value)}
                            className="flex items-center gap-1.5"
                        >
                            <RadioGroupItem value={String(c.value)} disabled />
                            {c.label}
                        </span>
                    ))}
                </RadioGroup>
            </div>
        </div>
    );
}

/** A value label, saved when leaving the field (empty: the default one) */
function ValueLabelInput({
    label,
    placeholder,
    value,
    onSave,
}: {
    label: string;
    placeholder: string;
    value: string;
    onSave: (value: string) => void;
}) {
    const [text, setText] = useState(value);
    const save = () => {
        const next = text.trim();
        if (next !== value) {
            onSave(next);
        }
    };

    return (
        <label className="flex items-center gap-2">
            <span className="w-28 shrink-0">{label}</span>
            <Input
                className="h-8 w-64 bg-background"
                value={text}
                maxLength={100}
                placeholder={placeholder}
                onChange={e => setText(e.target.value)}
                onBlur={save}
                onKeyDown={e => {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        save();
                    }
                }}
            />
        </label>
    );
}
