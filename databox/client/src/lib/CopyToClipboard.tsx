import copy from 'clipboard-copy';
import * as React from 'react';
import {Tooltip, TooltipProps} from '@mui/material';
import {useTranslation} from 'react-i18next';

type CopyFunc = (content: string | null | undefined) => void;

type ChildProps = {
    copy: CopyFunc;
};

type Props = {
    tooltipProps?: Partial<TooltipProps>;
    children: (props: ChildProps) => React.ReactElement;
};

export default function CopyToClipboard({
    children,
    tooltipProps = {},
}: Props) {
    const [show, setShow] = React.useState(false);
    const {t} = useTranslation();

    const onCopy: CopyFunc = (content) => {
        if (content) {
            copy(content);
            setShow(true);

            setTimeout(() => {
                setShow(false);
            }, 1000);
        }
    };

    const child = children({
        copy: onCopy,
    });

    return show ? <Tooltip
        open={true}
        title={t('copy_toclipboard.copied', 'Copied to clipboard!')}
        {...tooltipProps}
    >
        {child}
    </Tooltip> : child;
}
