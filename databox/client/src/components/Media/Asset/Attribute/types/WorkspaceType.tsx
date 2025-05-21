import {AttributeFormatterProps, AttributeTypeInstance,} from './types';
import React from 'react';
import BaseType from './BaseType.tsx';
import {Workspace} from '../../../../../types.ts';

import {WorkspaceChip} from "../../../../Ui/WorkspaceChip.tsx";

export default class WorkspaceType
    extends BaseType
    implements AttributeTypeInstance<Workspace> {
    renderWidget() {
        return <></>;
    }

    normalize(value: Workspace | undefined): string | undefined {
        return value?.id;
    }

    formatValue({value}: AttributeFormatterProps): React.ReactNode {
        return <WorkspaceChip
            label={value.nameTranslated || value.name}
            size={'small'}
        />
    }

    formatValueAsString({value}: AttributeFormatterProps): string | undefined {
        return value?.nameTranslated;
    }
}
