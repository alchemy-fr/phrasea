import {AttributeType} from "../../../../api/attributes";
import moment from "moment";

export function formatAttribute(type: AttributeType, value: any): string | undefined {
    if (!value) {
        return;
    }

    switch (type) {
        case AttributeType.Date:
            return moment(parseInt(value as string) * 1000).format('ll');
        default:
        case AttributeType.Text:
            return value.toString();
    }
}
