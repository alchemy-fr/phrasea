'use client';

import {useMemo, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {useRouter} from 'next/navigation';
import {AlertTriangleIcon, ListIcon, PlusIcon, XIcon} from 'lucide-react';
import type {AttributeDefinition} from '@/types/api';
import {AssetTypeFilter} from '@/types/api';
import {AttributeWidget} from '@/features/attributes/widgets/AttributeWidget';
import {
    AttrValue,
    AttributeIndex,
    DefinitionIndex,
} from './attributeEditorModel';
import {Button} from '@/components/ui/button';
import {Label} from '@/components/ui/input';
import {Tabs, TabsList, TabsTrigger, Alert} from '@/components/ui/misc';
import {NO_LOCALE, isRtl} from '@/lib/utils/locale';
import {routes} from '@/lib/routes';
import {idFromIri} from '@/lib/utils/iri';
import {shortId} from '@/lib/utils/misc';
import {cn} from '@/lib/utils/cn';
import {Flag} from '@/components/ui/flag';

export type OnAttributeChange = (
    definitionId: string,
    locale: string,
    value: AttrValue | AttrValue[] | undefined
) => void;

type Props = {
    attributes: AttributeIndex;
    definitions: DefinitionIndex;
    onChange: OnAttributeChange;
    disabled?: boolean;
    target: AssetTypeFilter;
    workspaceId?: string;
    workspaceLocales?: string[];
};

/**
 * Form of all GUI-editable attribute definitions of a workspace, with locale
 * tabs for translatable ones and add/remove rows for multi-valued ones.
 */
export function AttributesEditor({
    attributes,
    definitions,
    onChange,
    disabled,
    target,
    workspaceId,
    workspaceLocales,
}: Props) {
    const {t} = useTranslation();
    const router = useRouter();
    const list = useMemo(
        () =>
            Object.values(definitions)
                .filter(
                    d =>
                        d.editable &&
                        d.editableInGui &&
                        (!target || !d.target || (d.target & target) !== 0)
                )
                .sort((a, b) => (a.position ?? 0) - (b.position ?? 0)),
        [definitions, target]
    );
    const locales = useMemo(() => {
        const fromDefs = list.flatMap(d =>
            d.translatable ? (d.locales ?? []) : []
        );

        return [...new Set([...(workspaceLocales ?? []), ...fromDefs])];
    }, [list, workspaceLocales]);
    const [currentLocale, setCurrentLocale] = useState<string>(
        locales[0] ?? NO_LOCALE
    );

    if (list.length === 0) {
        return (
            <p className="py-4 text-center text-sm text-muted-foreground">
                {t('attribute.editor.none', 'No editable attribute')}
            </p>
        );
    }

    return (
        <div className="space-y-6">
            {list.map(def => {
                const translatable = def.translatable && locales.length > 0;
                const locale = translatable ? currentLocale : NO_LOCALE;
                const listId = def.entityList
                    ? typeof def.entityList === 'string'
                        ? idFromIri(def.entityList)
                        : def.entityList.id
                    : undefined;

                return (
                    <div key={def.id}>
                        <div className="mb-1.5 flex items-center gap-2">
                            <Label htmlFor={`attr-${def.id}`}>
                                {def.displayName ?? def.name}
                            </Label>
                            {!def.canEdit ? (
                                <span className="text-xs text-muted-foreground">
                                    ({t('attribute.read_only', 'read only')})
                                </span>
                            ) : null}
                            {listId && workspaceId ? (
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    className="ml-auto h-6 text-xs"
                                    onClick={() =>
                                        router.push(
                                            routes.workspaceManage(
                                                workspaceId,
                                                'entities'
                                            )
                                        )
                                    }
                                >
                                    <ListIcon />{' '}
                                    {t('attribute.manage_list', 'Manage list')}
                                </Button>
                            ) : null}
                        </div>
                        {translatable ? (
                            <Tabs
                                value={currentLocale}
                                onValueChange={setCurrentLocale}
                                className="mb-2"
                            >
                                <TabsList className="h-8">
                                    {locales.map(l => (
                                        <TabsTrigger
                                            key={l}
                                            value={l}
                                            className="h-6 px-2 text-xs"
                                        >
                                            <Flag locale={l} /> {l}
                                        </TabsTrigger>
                                    ))}
                                    <TabsTrigger
                                        value={NO_LOCALE}
                                        className="h-6 px-2 text-xs"
                                    >
                                        {t(
                                            'attribute.untranslated',
                                            'Untranslated'
                                        )}
                                    </TabsTrigger>
                                </TabsList>
                            </Tabs>
                        ) : def.translatable ? (
                            <Alert
                                variant="warning"
                                icon={<AlertTriangleIcon />}
                                className="mb-2 py-2"
                            >
                                {t(
                                    'attribute.no_locale',
                                    'This attribute is translatable but the workspace has no enabled locale.'
                                )}
                            </Alert>
                        ) : null}
                        <AttributeField
                            definition={def}
                            locale={locale}
                            value={attributes[def.id]?.[locale]}
                            onChange={v => onChange(def.id, locale, v)}
                            disabled={disabled || !def.canEdit}
                            workspaceId={workspaceId}
                        />
                    </div>
                );
            })}
        </div>
    );
}

export function AttributeField({
    definition,
    locale,
    value,
    onChange,
    disabled,
    workspaceId,
}: {
    definition: AttributeDefinition;
    locale: string;
    value: AttrValue | AttrValue[] | undefined;
    onChange: (v: AttrValue | AttrValue[] | undefined) => void;
    disabled?: boolean;
    workspaceId?: string;
}) {
    const {t} = useTranslation();
    const rtl = isRtl(locale === NO_LOCALE ? undefined : locale);

    if (definition.multiple) {
        const list = (value as AttrValue[] | undefined) ?? [];

        return (
            <div className="space-y-2">
                {list.map((item, i) => (
                    <div key={item.id} className="flex items-start gap-2">
                        <div className="flex-1">
                            <InvalidWrapper
                                item={item}
                                onCorrect={() =>
                                    onChange(
                                        list.map((x, j) =>
                                            j === i
                                                ? {
                                                      ...x,
                                                      invalid: false,
                                                      value: '',
                                                  }
                                                : x
                                        )
                                    )
                                }
                            >
                                <AttributeWidget
                                    id={`attr-${definition.id}-${i}`}
                                    definition={definition}
                                    value={item.value}
                                    onChange={v =>
                                        onChange(
                                            list.map((x, j) =>
                                                j === i ? {...x, value: v} : x
                                            )
                                        )
                                    }
                                    disabled={disabled}
                                    rtl={rtl}
                                    workspaceId={workspaceId}
                                    autoFocus={
                                        item.id.startsWith('new-') &&
                                        i === list.length - 1
                                    }
                                />
                            </InvalidWrapper>
                        </div>
                        <Button
                            variant="ghost"
                            size="icon-sm"
                            disabled={disabled}
                            onClick={() =>
                                onChange(list.filter((_, j) => j !== i))
                            }
                            aria-label={t('common.remove', 'Remove')}
                        >
                            <XIcon />
                        </Button>
                    </div>
                ))}
                <Button
                    variant="outline"
                    size="sm"
                    disabled={disabled}
                    onClick={() =>
                        onChange([...list, {id: `new-${shortId()}`, value: ''}])
                    }
                >
                    <PlusIcon />{' '}
                    {t('attribute.add_value', 'Add {{name}}', {
                        name: definition.displayName ?? definition.name,
                    })}
                </Button>
            </div>
        );
    }

    const single = value as AttrValue | undefined;

    return (
        <InvalidWrapper
            item={single}
            onCorrect={() =>
                onChange({
                    id: single?.id ?? `new-${shortId()}`,
                    value: '',
                    invalid: false,
                })
            }
        >
            <AttributeWidget
                id={`attr-${definition.id}`}
                definition={definition}
                value={single?.value}
                onChange={v =>
                    onChange(
                        v === undefined || v === ''
                            ? single?.id && !single.id.startsWith('new-')
                                ? {...single, value: undefined}
                                : undefined
                            : {id: single?.id ?? `new-${shortId()}`, value: v}
                    )
                }
                disabled={disabled}
                rtl={rtl}
                workspaceId={workspaceId}
            />
        </InvalidWrapper>
    );
}

function InvalidWrapper({
    item,
    onCorrect,
    children,
}: {
    item: AttrValue | undefined;
    onCorrect: () => void;
    children: React.ReactNode;
}) {
    const {t} = useTranslation();
    if (!item?.invalid) {
        return <>{children}</>;
    }

    return (
        <div
            className={cn(
                'rounded-md border border-warning/60 bg-warning/10 p-2'
            )}
        >
            <div className="mb-1 flex items-center gap-2 text-xs text-warning-foreground">
                <AlertTriangleIcon className="size-3.5" />
                {t('attribute.invalid_value', 'Invalid value: {{value}}', {
                    value: String(item.value),
                })}
                <Button
                    variant="outline"
                    size="sm"
                    className="ml-auto h-6 text-xs"
                    onClick={onCorrect}
                >
                    {t('attribute.correct', 'Correct')}
                </Button>
            </div>
        </div>
    );
}
