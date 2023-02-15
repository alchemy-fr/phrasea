import React, {PropsWithChildren} from 'react';
import {Asset} from "../../../../types";
import {formatAttribute} from "../../Asset/Attribute/AttributeFormatter";
import SectionDivider from "./SectionDivider";
import {AttributeFormatContext} from "../../Asset/Attribute/Format/AttributeFormatContext";
import {IconButton} from "@mui/material";
import VisibilityIcon from "@mui/icons-material/Visibility";

type Props = PropsWithChildren<{
    asset: Asset;
}>;

export default function GroupRow({
    asset,
    children,
}: Props) {
    const groupValue = asset.groupValue;
    const formatContext = React.useContext(AttributeFormatContext);

    const toggleFormatClass = 'toggle-format';

    if (!groupValue) {
        return <>{children}</>;
    }

    const {
        label,
        type,
    } = groupValue;

    return <>
        <SectionDivider
            dividerSx={{
                [`.${toggleFormatClass}`]: {
                    display: 'none',
                    position: 'absolute',
                    left: 0,
                    top: 0,
                    ml: 1,
                },
                ':hover': {
                    [`.${toggleFormatClass}`]: {
                        display: 'flex',
                    },
                }
            }}
        >
            {formatContext.hasFormats(type) && <IconButton
                className={toggleFormatClass}
                onClick={() => formatContext.toggleFormat(type)}
                sx={{
                    mr: 1,
                }}
            >
                <VisibilityIcon
                    fontSize={'small'}
                />
            </IconButton>}
            {formatAttribute(type, label, formatContext.formats[type])}
        </SectionDivider>
        {children}
    </>
}
