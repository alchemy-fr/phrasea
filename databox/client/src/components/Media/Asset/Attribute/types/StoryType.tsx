import {AttributeFormatterProps, AttributeTypeInstance} from './types';
import React from 'react';
import BaseType from './BaseType.tsx';
import {Asset} from '../../../../../types.ts';
import CollectionStoryChip from '../../../../Ui/CollectionStoryChip.tsx';

export default class StoryType
    extends BaseType
    implements AttributeTypeInstance<Asset>
{
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
                storyAsset={value.storyAsset}
                size={'small'}
            />
        );
    }

    formatValueAsString({value}: AttributeFormatterProps): string | undefined {
        return value?.titleTranslated || value.title;
    }
}
