import React from "react";
import AsyncSelectWidget, {AsyncSelectProps} from "../Form/AsyncSelectWidget";
import {SelectOption, TSelect} from "../Form/SelectWidget";
import {Group, User} from "../../types";
import {getGroups} from "../../api/user";

type Props = AsyncSelectProps;

const GroupSelect = React.forwardRef<TSelect, Props>(({
                                                          ...rest
                                                      }, ref) => {
    const load = async (inputValue?: string | undefined): Promise<SelectOption[]> => {
        const data = await getGroups();

        return data.map((t: Group) => ({
            value: t.id,
            label: t.name,
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

export default GroupSelect;
