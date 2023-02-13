import React, {PropsWithChildren} from 'react';
import {Asset} from "../../../../types";
import {formatAttribute} from "../../Asset/Attribute/AttributeFormatter";
import SectionDivider from "./SectionDivider";
import {AttributeFormatContext} from "../../Asset/Attribute/Format/AttributeFormatContext";

type Props = PropsWithChildren<{
    asset: Asset;
}>;

export default function GroupRow({
    asset,
    children,
}: Props) {
    const groupValue = asset.groupValue;
    const formatContext = React.useContext(AttributeFormatContext);

    if (!groupValue) {
        return <>{children}</>;
    }

    const {
        label,
        type,
    } = groupValue;

    return <>
        <SectionDivider>
            {formatAttribute(type, label, formatContext.formats[type])}
        </SectionDivider>
        {children}
    </>
}
