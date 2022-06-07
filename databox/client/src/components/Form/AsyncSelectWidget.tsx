import React, { useEffect, useState} from "react";
import SelectWidget, {SelectOption, SelectWidgetProps, TSelect} from "./SelectWidget";

export type AsyncSelectProps = Omit<SelectWidgetProps, 'options'>;

type Props = {
    load: (inputValue?: string | undefined) => Promise<SelectOption[]>;
} & AsyncSelectProps;

const AsyncSelectWidget = React.forwardRef<TSelect, Props>(({
                                                           load,
                                                           ...props
                                                       }, ref) => {
    const [options, setOptions] = useState<SelectOption[]>([]);

    useEffect(() => {
        load().then(data => setOptions(data));
    }, []);

    return <SelectWidget
        {...props}
        options={options}
        ref={ref}
    />
});

export default AsyncSelectWidget;
