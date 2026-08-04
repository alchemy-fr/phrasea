import {
    AttributeFormatterProps,
    AttributeTypeInstance,
    AttributeWidgetProps,
    AvailableFormat,
} from './types';
import React from 'react';
import {Box, InputLabel} from '@mui/material';
import {AttributeEntity, AttributeEntityStatus} from '../../../../../types.ts';
import AttributeEntitySelect from '../../../../Form/AttributeEntitySelect.tsx';
import BaseType from './BaseType.tsx';
import AttributeEntityListText from '../AttributeEntityListText.tsx';
import {getBestTranslatedValue} from '@alchemy/i18n/src/Locale/localeHelper.ts';
import {EntityName} from '../../../../../api/types.ts';

type EntityValue = {
    id: string;
    value: string | null;
    emoji?: string;
    color?: string;
    status: AttributeEntityStatus;
    createdAt: string;
};

export enum AttributeEntityFormats {
    Full = 'full',
    // Only the entity's emoji.
    Emoji = 'emoji',
    // Only the entity's color, as a swatch.
    Color = 'color',
}

export default class AttributeEntityType
    extends BaseType
    implements AttributeTypeInstance<string>
{
    public entityIri = EntityName.Entity;
    public isRich = true;

    renderWidget({
        labelAlreadyRendered,
        value,
        label,
        onChange,
        id,
        readOnly,
        disabled,
        options,
    }: AttributeWidgetProps<string>): React.ReactNode {
        return (
            <>
                {!labelAlreadyRendered && <InputLabel>{label}</InputLabel>}
                <AttributeEntitySelect
                    id={id}
                    multiple={false}
                    list={options.list}
                    disabled={readOnly || disabled}
                    value={value}
                    workspaceId={options.workspaceId}
                    onChange={newValue => {
                        onChange(
                            newValue && typeof newValue === 'object'
                                ? newValue.value
                                : (newValue as unknown as string | undefined)
                        );
                    }}
                />
            </>
        );
    }

    normalize(value: AttributeEntity | undefined): string | undefined {
        if (value && typeof value === 'string') {
            return value;
        }

        return value?.id;
    }

    formatValue(props: AttributeFormatterProps): React.ReactNode {
        const {value, format, t} = props;

        if (!value) {
            return null;
        }

        const status = (value as EntityValue | undefined)?.status;

        if (undefined !== status && status !== AttributeEntityStatus.Approved) {
            if (value.status === AttributeEntityStatus.Pending) {
                return (
                    <Box
                        component={'span'}
                        sx={{
                            color: 'warning.main',
                            fontStyle: 'italic',
                        }}
                    >
                        {t(
                            'attribute.entity.pending',
                            '[Value Pending for approval]'
                        )}
                    </Box>
                );
            } else {
                return null;
            }
        }

        const entity = value as EntityValue;

        if (AttributeEntityFormats.Emoji === format) {
            return <Box component={'span'}>{entity.emoji ?? '—'}</Box>;
        }

        if (AttributeEntityFormats.Color === format) {
            return (
                <Box
                    component={'span'}
                    sx={{
                        display: 'inline-block',
                        width: 14,
                        height: 14,
                        borderRadius: '50%',
                        border: '1px solid rgba(0,0,0,0.25)',
                        backgroundColor: entity.color || 'transparent',
                        flex: '0 0 auto',
                    }}
                />
            );
        }

        return <AttributeEntityListText data={value as AttributeEntity} />;
    }

    getAvailableFormats(): AvailableFormat[] {
        return [
            {
                name: AttributeEntityFormats.Full,
                title: 'Full',
            },
            {
                name: AttributeEntityFormats.Emoji,
                title: 'Emoji',
            },
            {
                name: AttributeEntityFormats.Color,
                title: 'Color',
            },
        ];
    }

    formatValueAsString({
        value,
        format,
    }: AttributeFormatterProps): string | undefined {
        if (!value) {
            return;
        }

        if (AttributeEntityFormats.Emoji === format) {
            return (value as EntityValue).emoji ?? undefined;
        }

        return getBestTranslatedValue(value.translations, value.value);
    }
}
