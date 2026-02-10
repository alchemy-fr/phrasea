import {AttributeFormatterProps, AttributeTypeInstance} from './types';
import React from 'react';
import BaseType from './BaseType.tsx';
import {User, Workspace} from '../../../../../types.ts';

import {UserChip} from '../../../../Ui/UserChip.tsx';

export default class UserType
    extends BaseType
    implements AttributeTypeInstance<Workspace>
{
    renderWidget() {
        return null;
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
        return value?.username;
    }
}
