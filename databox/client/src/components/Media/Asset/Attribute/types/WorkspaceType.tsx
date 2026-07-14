import {
    AttributeFormatterProps,
    AttributeTypeInstance,
    AttributeWidgetProps,
} from './types';
import React from 'react';
import BaseType from './BaseType.tsx';
import {Workspace} from '../../../../../types.ts';

import {WorkspaceChip} from '../../../../Ui/WorkspaceChip.tsx';
import WorkspaceSelect from '../../../../Form/WorkspaceSelect.tsx';
import {EntityName} from '../../../../../api/types.ts';
import {SelectOption} from '@alchemy/react-form';

export default class WorkspaceType
    extends BaseType
    implements AttributeTypeInstance<string>
{
    public entityIri = EntityName.Workspace;

    renderWidget({
        value,
        onChange,
        disabled,
    }: AttributeWidgetProps<string>): React.ReactNode {
        return (
            <WorkspaceSelect
                value={value}
                onChange={newValue => {
                    onChange(
                        newValue && typeof newValue === 'object'
                            ? (newValue as SelectOption).value
                            : (newValue as unknown as string | undefined)
                    );
                }}
                disabled={disabled}
            />
        );
    }

    normalize(value: Workspace | undefined): string | undefined {
        return value?.id;
    }

    formatValue({value}: AttributeFormatterProps): React.ReactNode {
        return <WorkspaceChip workspace={value} size={'small'} />;
    }

    formatValueAsString({value}: AttributeFormatterProps): string | undefined {
        return value?.displayName;
    }
}
