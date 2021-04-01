import {User} from "../../types";
import {getUsers} from "../../api/user";
import AbstractSelect, {UserOrGroupOption} from "./AbstractSelect";

export default class UserSelect extends AbstractSelect<User> {
    optionToData(option: UserOrGroupOption): User {
        return {
            id: option.value,
            username: option.label,
        };
    }

    getType(): string {
        return 'user';
    }

    dataToOption(data: User): UserOrGroupOption {
        return {
            value: data.id,
            label: data.username,
        };
    }

    async load() {
        return await getUsers();
    }
}
