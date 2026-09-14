import {AttributeFormatterProps, AttributeTypeInstance} from './types';
import React from 'react';
import BaseType from './BaseType.tsx';
import {Asset} from '../../../../../types.ts';
import CollectionStoryChip from '../../../../Ui/CollectionStoryChip.tsx';
import {EntityName} from '../../../../../api/types.ts';

export default class StoryType
    extends BaseType
    implements AttributeTypeInstance<Asset>
{
    public entityIri = EntityName.Asset;
    public isRich = true;

    renderWidget() {
        return null;
    }

    normalize(value: Asset | undefined): string | undefined {
        return value?.id;
    }

    formatValue({value}: AttributeFormatterProps): React.ReactNode {
        return (
            <CollectionStoryChip
                key={value.id}
                storyAsset={value}
                size={'small'}
            />
        );
    }

    formatValueAsString({value}: AttributeFormatterProps): string | undefined {
        if (value) {
            return value.name || value.id;
        }
    }
}
