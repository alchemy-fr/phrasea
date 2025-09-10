import {getAttributeType} from '../Media/Asset/Attribute/types';
import {AttributeType} from '../../api/types.ts';
import {useTranslation} from 'react-i18next';

type Props = {
    datetime: string;
};

export default function DateTime({datetime}: Props) {
    const {i18n} = useTranslation();

    return getAttributeType(AttributeType.DateTime).formatValue({
        value: datetime,
        uiLocale: i18n.language,
    });
}
