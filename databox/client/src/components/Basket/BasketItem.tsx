import {AssetItemCustomComponentProps} from "../AssetList/types.ts";
import {BasketAsset} from "../../types.ts";
import {styled} from "@mui/material/styles";

export default function BasketItem({
    item,
    children,
}: AssetItemCustomComponentProps<BasketAsset>) {

    return <div style={{
        position: 'relative',
    }}>
        <Number>{item.position.toString()}</Number>
        {children}
    </div>
}

const Number = styled('div')(({theme}) => ({
    position: 'absolute',
    zIndex: 1,
    left: 0,
    top: 0,
    backgroundColor: theme.palette.primary.main,
    color: theme.palette.primary.contrastText,
    fontSize: 28,
    padding: theme.spacing(1),
}));
