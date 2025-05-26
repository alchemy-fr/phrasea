import {Collection, Tag} from '../../../types';
import TagNode from '../../Ui/TagNode';

import React from 'react';
import {CollectionChip} from '../../Ui/CollectionChip.tsx';

export const groupValueTypes: Record<string, (value: any) => React.ReactNode> =
    {
        t: (value: Tag) => (
            <TagNode name={value.nameTranslated} color={value.color} />
        ),
        c: (value: Collection) => (
            <CollectionChip label={value.titleTranslated} />
        ),
    };
