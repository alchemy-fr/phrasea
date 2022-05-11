import React from "react";
import AsyncSelectWidget, {AsyncSelectProps} from "../Form/AsyncSelectWidget";
import {SelectOption, TSelect} from "../Form/SelectWidget";
import {User} from "../../types";
import {getUsers} from "../../api/user";

type Props = AsyncSelectProps;

const UserSelect = React.forwardRef<TSelect, Props>(({
                                                         ...rest
                                                     }, ref) => {
    const load = async (inputValue?: string | undefined): Promise<SelectOption[]> => {
        const data = await getUsers();

        return data.map((t: User) => ({
            value: t.id,
            label: t.username,
        })).filter(i =>
            i.label.toLowerCase().includes((inputValue || '').toLowerCase())
        );
    };

    return <AsyncSelectWidget
        load={load}
        {...rest}
        ref={ref}
    />
});

export default UserSelect;
