import {SvgIconComponent} from "@mui/icons-material";
import TextFieldsIcon from "@mui/icons-material/TextFields";
import CheckBoxIcon from "@mui/icons-material/CheckBox";
import CalendarTodayIcon from "@mui/icons-material/CalendarToday";
import LooksOneIcon from "@mui/icons-material/LooksOne";
import AlternateEmailIcon from "@mui/icons-material/AlternateEmail";
import DataObjectIcon from '@mui/icons-material/DataObject';
import {AttributeType} from "../api/attributes";
import HtmlIcon from '@mui/icons-material/Html';
import CodeIcon from '@mui/icons-material/Code';
import ColorLensIcon from '@mui/icons-material/ColorLens';
import SubjectIcon from '@mui/icons-material/Subject';
import LocationOnIcon from '@mui/icons-material/LocationOn';
import AbcIcon from '@mui/icons-material/Abc';

export const fieldTypesIcons: Record<string, SvgIconComponent> = {
    [AttributeType.Boolean]: CheckBoxIcon,
    [AttributeType.Code]: CodeIcon,
    [AttributeType.Color]: ColorLensIcon,
    [AttributeType.DateTime]: CalendarTodayIcon,
    [AttributeType.Date]: CalendarTodayIcon,
    [AttributeType.GeoPoint]: LocationOnIcon,
    [AttributeType.Html]: HtmlIcon,
    [AttributeType.Ip]: AlternateEmailIcon,
    [AttributeType.Json]: DataObjectIcon,
    [AttributeType.Keyword]: AbcIcon,
    [AttributeType.Number]: LooksOneIcon,
    [AttributeType.Text]: TextFieldsIcon,
    [AttributeType.WebVtt]: SubjectIcon,
}
