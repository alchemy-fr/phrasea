import {AssetItemCustomComponentProps} from '../AssetList/types';
import {BasketAsset} from '../../types';
import {styled} from '@mui/material/styles';

export default function BasketItem({
    item,
    children,
}: AssetItemCustomComponentProps<BasketAsset>) {
    return (
        <div
            style={{
                position: 'relative',
            }}
        >
            <Number>{item.position.toString()}</Number>
            {children}
        </div>
    );
}

const Number = styled('div')(({theme}) => ({
    position: 'absolute',
    zIndex: 1,
    left: 0,
    top: 0,
    backgroundColor: theme.palette.primary.main,
    color: theme.palette.primary.contrastText,
    fontSize: 15,
    padding: `${theme.spacing(0.5)} ${theme.spacing(1)}`,
    borderBottomRightRadius: theme.shape.borderRadius,
}));
