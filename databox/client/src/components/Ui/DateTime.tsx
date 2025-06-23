import {getAttributeType} from '../Media/Asset/Attribute/types';
import {AttributeType} from '../../api/types.ts';

type Props = {
    datetime: string;
};

export default function DateTime({datetime}: Props) {
    return (
        <>
            {getAttributeType(AttributeType.DateTime).formatValue({
                value: datetime,
            })}
        </>
    );
}
