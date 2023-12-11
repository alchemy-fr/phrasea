import {AttributeType} from '../../api/attributes';
import {getAttributeType} from '../Media/Asset/Attribute/types';

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
