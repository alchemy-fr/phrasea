import {PropsWithChildren, ReactNode} from 'react';
import {LoadingButtonProps} from '@mui/lab';
import type {AppDialogProps} from '@alchemy/phrasea-ui';
import {StackedModalProps} from '@alchemy/navigation';

export type ConfirmOptions = {[key: string]: ReactNode};
export type ConfirmOptionValues<CO extends ConfirmOptions> = {
    [key in keyof CO]: boolean;
};

export type ConfirmDialogProps<CO extends ConfirmOptions> = PropsWithChildren<
    {
        onCancel?: (() => void) | undefined;
        onConfirm: (options: ConfirmOptionValues<CO>) => Promise<void | false>;
        onConfirmed?: () => void;
        title?: ReactNode;
        options?: CO;
        confirmLabel?: ReactNode;
        disabled?: boolean;
        textToType?: string | undefined;
        assertions?: ReactNode[] | undefined;
        confirmButtonProps?: LoadingButtonProps | undefined;
        maxWidth?: AppDialogProps['maxWidth'];
    } & StackedModalProps
>;
