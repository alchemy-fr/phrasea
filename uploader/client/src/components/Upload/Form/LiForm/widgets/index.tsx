import StringWidget from './widgets/StringWidget';
import TextareaWidget from './widgets/TextareaWidget.tsx';
import EmailWidget from './widgets/EmailWidget';
import NumberWidget from './widgets/NumberWidget';
import MoneyWidget from './widgets/MoneyWidget';
import PercentWidget from './widgets/PercentWidget';
import ArrayWidget from './widgets/ArrayWidget';
import CheckboxWidget from './widgets/CheckboxWidget';
import ObjectWidget from './widgets/ObjectWidget.tsx';
import PasswordWidget from './widgets/PasswordWidget';
import SearchWidget from './widgets/SearchWidget';
import UrlWidget from './widgets/UrlWidget.tsx';
import ColorWidget from './widgets/ColorWidget';
import ChoiceWidget from './widgets/ChoiceWidget';
import OneOfChoiceWidget from './widgets/oneOfChoiceWidget';
import DateWidget from './widgets/DateWidget';
import TimeWidget from './widgets/TimeWidget';
import DateTimeWidget from './widgets/DateTimeWidget';
import CompatibleDateWidget from './widgets/CompatibleDateWidget';
export default {
    'object': ObjectWidget,
    'string': StringWidget,
    'textarea': TextareaWidget,
    'email': EmailWidget,
    'integer': NumberWidget,
    'number': NumberWidget,
    'money': MoneyWidget,
    'percent': PercentWidget,
    'array': ArrayWidget,
    'boolean': CheckboxWidget,
    'password': PasswordWidget,
    'search': SearchWidget,
    'url': UrlWidget,
    'color': ColorWidget,
    'choice': ChoiceWidget,
    'date': DateWidget,
    'datetime': DateTimeWidget,
    'time': TimeWidget,
    'OneOfChoiceWidget': OneOfChoiceWidget,
    'oneOf': OneOfChoiceWidget,
    'compatible-date': CompatibleDateWidget,
};
