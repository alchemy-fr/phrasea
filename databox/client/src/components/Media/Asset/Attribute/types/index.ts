import {AttributeType} from '../../../../../api/attributes';
import TextType from './TextType';
import DateType from './DateType';
import {AttributeTypeClass, AttributeTypeInstance} from './types';
import TextareaType from './TextareaType';
import JsonType from './JsonType';
import CodeType from './CodeType';
import BooleanType from './BooleanType';
import HtmlType from './HtmlType';
import ColorType from './ColorType';
import GeoPointType from './GeoPointType';
import DateTimeType from './DateTimeType';
import TagsType from './TagsType.tsx';
import AttributeEntityType from './AttributeEntityType.tsx';

export const types: {
    [key in AttributeType]?: AttributeTypeClass;
} = {
    [AttributeType.Boolean]: BooleanType,
    [AttributeType.Code]: CodeType,
    [AttributeType.Color]: ColorType,
    [AttributeType.DateTime]: DateTimeType,
    [AttributeType.Date]: DateType,
    [AttributeType.Html]: HtmlType,
    [AttributeType.Json]: JsonType,
    [AttributeType.Text]: TextType,
    [AttributeType.Textarea]: TextareaType,
    [AttributeType.GeoPoint]: GeoPointType,
    [AttributeType.WebVtt]: CodeType,
    [AttributeType.Tag]: TagsType,
    [AttributeType.Entity]: AttributeEntityType,
};

export function getAttributeType(type: string): AttributeTypeInstance<any> {
    const t = types[type as AttributeType] ?? types[AttributeType.Text]!;

    return new t();
}
