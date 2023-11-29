import {Collection, Tag} from '../../../../../types';
import TagNode from '../../../../Ui/TagNode';

import {CollectionChip} from '../../../../Ui/Chips';

export const groupValueTypes: Record<string, (value: any) => React.ReactNode> =
    {
        t: (value: Tag) => <TagNode name={value.name} color={value.color} />,
        c: (value: Collection) => <CollectionChip label={value.title} />,
    };
