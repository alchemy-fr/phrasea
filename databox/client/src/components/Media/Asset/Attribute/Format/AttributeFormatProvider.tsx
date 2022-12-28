import React, {PropsWithChildren} from 'react';
import {AttributeFormatContext, AttributeFormats, TAttributeFormatContext,} from "./AttributeFormatContext";

type Props = PropsWithChildren<{}>;

export default function AttributeFormatProvider({children}: Props) {
    const [formats, setFormats] = React.useState<AttributeFormats>({});

    const value = React.useMemo<TAttributeFormatContext>(() => {
        const changeFormat: TAttributeFormatContext['changeFormat'] = (type, format) => {
            setFormats(p => ({
                ...p,
                [type]: format
            }));
        }

        return {
            changeFormat,
            formats,
        };
    }, [formats, setFormats]);

    return <AttributeFormatContext.Provider value={value}>
        {children}
    </AttributeFormatContext.Provider>
}
