import {
    AttributeFormatterProps,
    AttributeTypeInstance,
    AttributeWidgetProps,
} from './types';
import React from 'react';
import BaseType from './BaseType.tsx';
import {User} from '../../../../../types.ts';

import {UserChip} from '../../../../Ui/UserChip.tsx';
import UserSelect from '../../../../Form/UserSelect.tsx';
import {EntityName} from '../../../../../api/types.ts';
import {SelectOption} from '@alchemy/react-form';

export default class UserType
    extends BaseType
    implements AttributeTypeInstance<string>
{
    entityIri = EntityName.User;

    renderWidget({
        value,
        onChange,
        disabled,
    }: AttributeWidgetProps<string>): React.ReactNode {
        return (
            <UserSelect
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

    normalize(value: User | undefined): string | undefined {
        return value?.id;
    }

    formatValue({value}: AttributeFormatterProps): React.ReactNode {
        if (value) {
            return <UserChip user={value} size={'small'} />;
        }
    }

    formatValueAsString({value}: AttributeFormatterProps): string | undefined {
        if (value) {
            return value.username || value.id;
        }
    }
}
