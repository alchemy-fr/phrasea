import TextType from './TextType';
import DateType from './DateType';
import {
    AttributeFormatterProps,
    AttributeTypeClass,
    AttributeTypeInstance,
} from './types';
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
import {ReactNode} from 'react';
import WorkspaceType from './WorkspaceType.tsx';
import CollectionType from './CollectionType.tsx';
import PrivacyType from './PrivacyType.tsx';
import UserType from './UserType.tsx';
import {AttributeType} from '../../../../../api/types.ts';

export const types: {
    [key in AttributeType]?: AttributeTypeClass;
} = {
    [AttributeType.Boolean]: BooleanType,
    [AttributeType.Code]: CodeType,
    [AttributeType.CollectionPath]: CollectionType,
    [AttributeType.Color]: ColorType,
    [AttributeType.DateTime]: DateTimeType,
    [AttributeType.Date]: DateType,
    [AttributeType.Entity]: AttributeEntityType,
    [AttributeType.GeoPoint]: GeoPointType,
    [AttributeType.Html]: HtmlType,
    [AttributeType.Json]: JsonType,
    [AttributeType.Tag]: TagsType,
    [AttributeType.Text]: TextType,
    [AttributeType.Textarea]: TextareaType,
    [AttributeType.WebVtt]: CodeType,
    [AttributeType.Workspace]: WorkspaceType,
    [AttributeType.Privacy]: PrivacyType,
    [AttributeType.User]: UserType,
};

export function getAttributeType(
    type: AttributeType
): AttributeTypeInstance<any> {
    const t = types[type] ?? types[AttributeType.Text]!;

    return new t();
}

export function formatValue(
    type: AttributeType,
    props: AttributeFormatterProps
): ReactNode | undefined {
    const attributeType = getAttributeType(type);
    return attributeType.formatValue(props);
}
