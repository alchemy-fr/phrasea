import {AttributeType} from "../../../../../api/attributes";
import TextType from "./TextType";
import DateType from "./DateType";
import {AttributeTypeClass} from "./types";
import TextareaType from "./TextareaType";
import JsonType from "./JsonType";
import CodeType from "./CodeType";
import BooleanType from "./BooleanType";
import HtmlType from "./HtmlType";
import ColorType from "./ColorType";
import GeoPointType from "./GeoPointType";

export const types: Record<string, AttributeTypeClass> = {
    [AttributeType.Boolean]: BooleanType,
    [AttributeType.Code]: CodeType,
    [AttributeType.Color]: ColorType,
    [AttributeType.DateTime]: DateType,
    [AttributeType.Date]: DateType,
    [AttributeType.Html]: HtmlType,
    [AttributeType.Json]: JsonType,
    [AttributeType.Text]: TextType,
    [AttributeType.Textarea]: TextareaType,
    [AttributeType.GeoPoint]: GeoPointType,
    [AttributeType.WebVtt]: CodeType,
}
