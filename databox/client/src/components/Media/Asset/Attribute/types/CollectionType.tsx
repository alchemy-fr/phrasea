import {AttributeFormatterProps, AttributeTypeInstance} from './types';
import React from 'react';
import BaseType from './BaseType.tsx';
import {Collection} from '../../../../../types.ts';
import {CollectionChip} from '../../../../Ui/CollectionChip.tsx';
import {EntityName} from '../../../../../api/types.ts';

export default class CollectionType
    extends BaseType
    implements AttributeTypeInstance<Collection>
{
    public entityIri = EntityName.Collection;

    renderWidget() {
        return null;
    }

    normalize(value: Collection | undefined): string | undefined {
        return value?.id;
    }

    formatValue({value}: AttributeFormatterProps): React.ReactNode {
        return <CollectionChip collection={value} size={'small'} />;
    }

    formatValueAsString({value}: AttributeFormatterProps): string | undefined {
        return value?.displayName;
    }
}
