import {Group} from "../../types";
import {getGroups} from "../../api/user";
import AbstractSelect, {UserOrGroupOption} from "./AbstractSelect";

export default class GroupSelect extends AbstractSelect<Group> {
    optionToData(option: UserOrGroupOption): Group {
        return {
            id: option.value,
            name: option.label,
        };
    }

    dataToOption(data: Group): UserOrGroupOption {
        return {
            value: data.id,
            label: data.name,
        };
    }

    getType(): string {
        return 'group';
    }

    async load() {
        return await getGroups();
    }
}
