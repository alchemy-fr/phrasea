import {AttributeFormatterProps, AttributeTypeInstance} from './types';
import React from 'react';
import BaseType from './BaseType.tsx';
import {Collection} from '../../../../../types.ts';
import {CollectionChip} from '../../../../Ui/CollectionChip.tsx';

export default class CollectionType
    extends BaseType
    implements AttributeTypeInstance<Collection>
{
    renderWidget() {
        return <></>;
    }

    normalize(value: Collection | undefined): string | undefined {
        return value?.id;
    }

    formatValue({value}: AttributeFormatterProps): React.ReactNode {
        return (
            <CollectionChip
                label={value.titleTranslated || value.title}
                size={'small'}
            />
        );
    }

    formatValueAsString({value}: AttributeFormatterProps): string | undefined {
        return value?.titleTranslated || value.title;
    }
}
