import React, {PropsWithChildren} from 'react';
import {Asset} from "../../../../types";
import {formatAttribute} from "../../Asset/Attribute/AttributeFormatter";
import SectionDivider from "./SectionDivider";

type Props = PropsWithChildren<{
    asset: Asset;
}>;

export default function GroupRow({
                                     asset,
                                     children,
                                 }: Props) {
    const groupValue = asset.groupValue;
    if (!groupValue) {
        return <>{children}</>;
    }

    const {
        label,
        type,
    } = groupValue;

    return <>
        <SectionDivider
            rootStyle={theme => ({
                margin: `0 -${theme.spacing(1)}`,
                width: `calc(100% + ${theme.spacing(2)})`,
            })}
        >
            {formatAttribute(type, label)}
        </SectionDivider>
        {children}
    </>
}
