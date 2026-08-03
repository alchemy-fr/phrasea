import TextType from './TextType';
import DateType from './DateType';
import {AttributeTypeClass} from './types';
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
import WorkspaceType from './WorkspaceType.tsx';
import CollectionType from './CollectionType.tsx';
import PrivacyType from './PrivacyType.tsx';
import UserType from './UserType.tsx';
import {AttributeType} from '../../../../../api/types.ts';
import NumberType from './NumberType.tsx';
import StoryType from './StoryType.tsx';
import DurationType from './DurationType.tsx';
import FileSizeType from './FileSizeType.tsx';
import AssetStatusType from './AssetStatusType.tsx';
import RenditionDefinitionType from './RenditionDefinitionType.tsx';
import FileTypeType from './FileTypeType.tsx';

export const types: {
    [key in AttributeType]?: AttributeTypeClass<any>;
} = {
    [AttributeType.Boolean]: BooleanType,
    [AttributeType.Code]: CodeType,
    [AttributeType.CollectionPath]: CollectionType,
    [AttributeType.Story]: StoryType,
    [AttributeType.Color]: ColorType,
    [AttributeType.DateTime]: DateTimeType,
    [AttributeType.Date]: DateType,
    [AttributeType.AttributeEntity]: AttributeEntityType,
    [AttributeType.GeoPoint]: GeoPointType,
    [AttributeType.Html]: HtmlType,
    [AttributeType.Json]: JsonType,
    [AttributeType.Tag]: TagsType,
    [AttributeType.Text]: TextType,
    [AttributeType.Textarea]: TextareaType,
    [AttributeType.WebVtt]: CodeType,
    [AttributeType.Workspace]: WorkspaceType,
    [AttributeType.Rendition]: RenditionDefinitionType,
    [AttributeType.Privacy]: PrivacyType,
    [AttributeType.AssetStatus]: AssetStatusType,
    [AttributeType.User]: UserType,
    [AttributeType.Number]: NumberType,
    [AttributeType.Duration]: DurationType,
    [AttributeType.FileSize]: FileSizeType,
    [AttributeType.FileType]: FileTypeType,
};
