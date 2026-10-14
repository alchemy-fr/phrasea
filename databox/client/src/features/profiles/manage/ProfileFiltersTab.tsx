'use client';

import {useMemo, useState} from 'react';
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
import {Button} from '@/components/ui/button';
import {aqlKey} from '@/features/attributes/definitionsStore';
import {booleanChoices} from '@/features/search/pinned/booleanChoices';
import {
    PinnedFilterWidget,
    type PinnedFilter,
} from '@/features/search/pinned/PinnedFilters';
import {pinnedConditionId} from '@/features/search/pinned/pinnedFilterQuery';
import {parseAQL} from '@/features/search/aql/parser';
import {HumanizedExpression} from '@/features/search/conditions/SearchConditionChip';
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
            badges={item => (
                <>
                    {item.widget && item.widget !== FilterWidget.Select ? (
                        <Badge variant="muted">
                            {widgetLabels(t)[item.widget]}
                        </Badge>
                    ) : null}
                    {item.defaultQuery ? (
                        <Badge variant="muted" title={item.defaultQuery}>
                            {t('profile.filters.has_default', 'Default value')}
                        </Badge>
                    ) : null}
                </>
            )}
            options={(item, type, onChange, definition) =>
                definition ? (
                    <div className="space-y-3 border-t bg-muted/30 px-3 py-2.5 text-xs text-muted-foreground">
                        {type === AttributeType.Boolean ? (
                            <BooleanFilterOptions
                                item={item}
                                onChange={onChange}
                            />
                        ) : null}
                        <DefaultValueEditor
                            item={item}
                            definition={definition}
                            onChange={onChange}
                        />
                    </div>
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
        <>
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
        </>
    );
}

/**
 * The value the filter applies to every search that does not set it,
 * picked with the widget of the search bar.
 */
function DefaultValueEditor({
    item,
    definition,
    onChange,
}: {
    item: ProfileItem;
    definition: AttributeDefinitionOrBuiltIn;
    onChange: (data: Partial<ProfileItem>) => void;
}) {
    const {t} = useTranslation();
    const filter = useMemo<PinnedFilter>(() => {
        const field = aqlKey(definition);

        return {item, definition, field, conditionId: pinnedConditionId(field)};
    }, [item, definition]);
    const ast = useMemo(
        () => (item.defaultQuery ? parseAQL(item.defaultQuery) : undefined),
        [item.defaultQuery]
    );
    const apply = (query: string | undefined) =>
        onChange({defaultQuery: query ?? ''});

    return (
        <div className="space-y-2" data-testid="profile-filter-default">
            <div className="flex min-h-7 items-center gap-2">
                <span className="w-28 shrink-0">
                    {t('profile.filters.default', 'Default value')}
                </span>
                {ast ? (
                    <>
                        <span className="min-w-0 truncate text-sm text-foreground">
                            <HumanizedExpression expression={ast.expression} />
                        </span>
                        <Button
                            variant="ghost"
                            size="sm"
                            className="h-7"
                            onClick={() => apply(undefined)}
                        >
                            {t('common.clear', 'Clear')}
                        </Button>
                    </>
                ) : (
                    <span className="italic">
                        {t('profile.filters.no_default', 'None')}
                    </span>
                )}
            </div>
            <p>
                {t(
                    'profile.filters.default_help',
                    'Applied to every search, including the home page and a reset search, until the user picks another value or clears it.'
                )}
            </p>
            <div className="w-fit rounded-md border bg-background text-foreground">
                <PinnedFilterWidget
                    // Remounted when cleared: the form widgets drop their input
                    key={ast ? 'set' : 'none'}
                    filter={filter}
                    ast={ast}
                    onApply={apply}
                />
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
