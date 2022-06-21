import {SvgIconComponent} from "@mui/icons-material";
import TextFieldsIcon from "@mui/icons-material/TextFields";
import CheckBoxIcon from "@mui/icons-material/CheckBox";
import CalendarTodayIcon from "@mui/icons-material/CalendarToday";
import LooksOneIcon from "@mui/icons-material/LooksOne";
import AlternateEmailIcon from "@mui/icons-material/AlternateEmail";

export const fieldTypesIcons: Record<string, SvgIconComponent> = {
    text: TextFieldsIcon,
    boolean: CheckBoxIcon,
    date: CalendarTodayIcon,
    datetime: CalendarTodayIcon,
    number: LooksOneIcon,
    ip: AlternateEmailIcon,
}
