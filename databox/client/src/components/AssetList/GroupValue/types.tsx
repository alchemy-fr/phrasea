import {Collection, Tag} from '../../../types.ts';
import TagNode from '../../Ui/TagNode.tsx';

import {CollectionChip} from '../../Ui/Chips.tsx';
import React from 'react';

export const groupValueTypes: Record<string, (value: any) => React.ReactNode> =
    {
        t: (value: Tag) => <TagNode name={value.nameTranslated} color={value.color} />,
        c: (value: Collection) => <CollectionChip label={value.title} />,
    };
