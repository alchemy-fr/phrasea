'use client';

import {useTranslation} from 'react-i18next';
import {
    AttributeDefinition,
    AttributeType,
    Privacy,
    AssetStatus,
} from '@/types/api';
import {Input, Textarea} from '@/components/ui/input';
import {SimpleSelect} from '@/components/ui/select';
import {
    AttributeEntitySelect,
    TagSelect,
    UserSelect,
} from '@/components/form/selects';
import {PrivacyField} from '@/components/form/PrivacyField';
import {assetStatusLabels} from '@/features/attributes/types/registry';
import {idFromIri} from '@/lib/utils/iri';
import {cn} from '@/lib/utils/cn';

export type AttributeWidgetProps = {
    id: string;
    definition: AttributeDefinition;
    value: unknown;
    onChange: (value: unknown) => void;
    disabled?: boolean;
    readOnly?: boolean;
    autoFocus?: boolean;
    /** bulk editor: several assets have different values */
    indeterminate?: boolean;
    rtl?: boolean;
    workspaceId?: string;
    onKeyDown?: React.KeyboardEventHandler;
    className?: string;
};

function str(v: unknown): string {
    return v === null || v === undefined
        ? ''
        : typeof v === 'object'
          ? JSON.stringify(v, null, 2)
          : String(v);
}

/**
 * Edit widget for an attribute value, picked from the definition type.
 */
export function AttributeWidget(props: AttributeWidgetProps) {
    const {t} = useTranslation();
    const {
        id,
        definition,
        value,
        onChange,
        disabled,
        readOnly,
        autoFocus,
        indeterminate,
        rtl,
        workspaceId,
        onKeyDown,
        className,
    } = props;
    const common = {
        id,
        disabled,
        readOnly,
        autoFocus,
        dir: rtl ? ('rtl' as const) : undefined,
        onKeyDown,
        placeholder: indeterminate
            ? t('attribute.multiple_values', '[multiple values]')
            : undefined,
        className: cn(
            indeterminate && 'italic placeholder:text-warning-foreground',
            className
        ),
    };

    switch (definition.type) {
        case AttributeType.Textarea:
            return (
                <Textarea
                    {...common}
                    value={str(value)}
                    onChange={e => onChange(e.target.value)}
                />
            );
        case AttributeType.Code:
        case AttributeType.Json:
        case AttributeType.Html:
        case AttributeType.WebVtt:
            return (
                <Textarea
                    {...common}
                    value={str(value)}
                    onChange={e => onChange(e.target.value)}
                    className={cn(
                        'min-h-32 font-mono text-xs',
                        common.className
                    )}
                    spellCheck={false}
                />
            );
        case AttributeType.Number:
        case AttributeType.Duration:
        case AttributeType.FileSize:
            return (
                <Input
                    {...common}
                    type="number"
                    step="any"
                    value={str(value)}
                    onChange={e =>
                        onChange(
                            e.target.value === ''
                                ? undefined
                                : Number(e.target.value)
                        )
                    }
                />
            );
        case AttributeType.Date:
            return (
                <Input
                    {...common}
                    type="date"
                    value={str(value).slice(0, 10)}
                    onChange={e => onChange(e.target.value || undefined)}
                />
            );
        case AttributeType.DateTime:
            return (
                <Input
                    {...common}
                    type="datetime-local"
                    step={1}
                    value={str(value).slice(0, 19)}
                    onChange={e => onChange(e.target.value || undefined)}
                />
            );
        case AttributeType.Boolean:
            return (
                <SimpleSelect
                    value={
                        value === true
                            ? 'true'
                            : value === false
                              ? 'false'
                              : indeterminate
                                ? undefined
                                : 'null'
                    }
                    onValueChange={v =>
                        onChange(
                            v === 'true'
                                ? true
                                : v === 'false'
                                  ? false
                                  : undefined
                        )
                    }
                    disabled={disabled || readOnly}
                    placeholder={
                        indeterminate
                            ? t(
                                  'attribute.multiple_values',
                                  '[multiple values]'
                              )
                            : undefined
                    }
                    options={[
                        {
                            value: 'null',
                            label: t('attribute.boolean.not_set', 'Not set'),
                        },
                        {value: 'true', label: t('common.yes', 'Yes')},
                        {value: 'false', label: t('common.no', 'No')},
                    ]}
                />
            );
        case AttributeType.Color:
            return (
                <div className="flex items-center gap-2">
                    <input
                        type="color"
                        id={id}
                        value={
                            /^#[0-9a-f]{6}$/i.test(str(value))
                                ? str(value)
                                : '#000000'
                        }
                        onChange={e => onChange(e.target.value)}
                        disabled={disabled || readOnly}
                        className="size-9 cursor-pointer rounded border bg-transparent"
                    />
                    <Input
                        value={str(value)}
                        onChange={e => onChange(e.target.value || undefined)}
                        placeholder="#rrggbb"
                        className="w-32 font-mono"
                        disabled={disabled}
                        readOnly={readOnly}
                    />
                </div>
            );
        case AttributeType.GeoPoint: {
            const text =
                value && typeof value === 'object'
                    ? `${(value as any).lat}, ${(value as any).lng}`
                    : str(value);

            return (
                <Input
                    {...common}
                    value={text}
                    placeholder={common.placeholder ?? 'lat, lng'}
                    onChange={e => onChange(e.target.value || undefined)}
                />
            );
        }
        case AttributeType.Tag:
            return (
                <TagSelect
                    id={id}
                    workspaceId={workspaceId}
                    disabled={disabled || readOnly}
                    value={
                        value
                            ? typeof value === 'object'
                                ? (value as any).id
                                : idFromIri(String(value))
                            : undefined
                    }
                    onChange={v => onChange(v)}
                />
            );
        case AttributeType.Entity: {
            const listId = definition.entityList
                ? typeof definition.entityList === 'string'
                    ? idFromIri(definition.entityList)
                    : definition.entityList.id
                : undefined;
            const allowNew =
                typeof definition.entityList === 'object'
                    ? definition.entityList?.allowNewValues
                    : true;

            return (
                <AttributeEntitySelect
                    id={id}
                    listId={listId}
                    allowNewValues={allowNew}
                    disabled={disabled || readOnly}
                    value={
                        value
                            ? typeof value === 'object'
                                ? (value as any).id
                                : String(value)
                            : undefined
                    }
                    onChange={v => onChange(v)}
                />
            );
        }
        case AttributeType.User:
            return (
                <UserSelect
                    id={id}
                    disabled={disabled || readOnly}
                    value={
                        value
                            ? typeof value === 'object'
                                ? (value as any).id
                                : String(value)
                            : undefined
                    }
                    onChange={v => onChange(v)}
                />
            );
        case AttributeType.Privacy:
            return (
                <PrivacyField
                    value={
                        typeof value === 'number'
                            ? (value as Privacy)
                            : undefined
                    }
                    onChange={v => onChange(v)}
                    disabled={disabled || readOnly}
                    allowUnset
                    label={definition.displayName}
                />
            );
        case AttributeType.AssetStatus:
            return (
                <SimpleSelect
                    value={
                        value === undefined || value === null
                            ? undefined
                            : String(value)
                    }
                    onValueChange={v => onChange(Number(v))}
                    disabled={disabled || readOnly}
                    options={Object.entries(assetStatusLabels(t)).map(
                        ([k, label]) => ({value: k, label})
                    )}
                />
            );
        case AttributeType.Text:
        default:
            return (
                <Input
                    {...common}
                    type="text"
                    value={str(value)}
                    onChange={e => onChange(e.target.value)}
                />
            );
    }
}

export {AssetStatus};
