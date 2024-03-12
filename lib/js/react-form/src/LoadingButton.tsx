import {LoadingButton as BaseLoadingButton, LoadingButtonProps} from '@mui/lab';

export default function LoadingButton({children, ...rest}: LoadingButtonProps) {
    if (children) {
        // https://github.com/facebook/react/issues/11538#issuecomment-390386520
        children = <span>{children}</span>;
    }

    return <BaseLoadingButton {...rest}>{children}</BaseLoadingButton>;
}
