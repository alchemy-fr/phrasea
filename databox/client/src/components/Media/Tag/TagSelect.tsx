import React from "react";
import {Tag} from "../../../types";
import {getTags} from "../../../api/tag";
import AsyncSelectWidget from "../../Form/AsyncSelectWidget";
import {SelectOption, SelectWidgetProps, TSelect} from "../../Form/SelectWidget";

type Props = {
    workspaceId: string;
    value: Tag[],
} & SelectWidgetProps;

const TagSelect = React.forwardRef<TSelect, Props>(({
                                                        workspaceId,
                                                        ...rest
                                                    }, ref) => {
    const load = async (inputValue?: string | undefined): Promise<SelectOption[]> => {
        const data = (await getTags({
            //query: inputValue,
            workspace: workspaceId,
        })).result;

        return data.map((t: Tag) => ({
            value: t.id,
            label: t.name,
            data: t,
        })).filter(i =>
            i.label.toLowerCase().includes((inputValue || '').toLowerCase())
        );
    };

    return <AsyncSelectWidget
        {...rest}
        load={load}
        ref={ref}
    />
});

export default TagSelect;
