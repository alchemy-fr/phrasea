import React, {MouseEventHandler, PropsWithChildren} from 'react';
import {IconType} from "react-icons";

const variants = {
    primary: {
        color: '#FFF',
        backgroundColor: '#10b075',
    },
    default: {
        color: '#000',
        backgroundColor: '#FFF',
        border: `1px solid #CCC`,
    },
}

type Props = PropsWithChildren<{
    onClick?: MouseEventHandler<HTMLButtonElement>;
    loading?: boolean | undefined;
    disabled?: boolean | undefined;
    variant?: keyof typeof variants;
    icon?: IconType;
}>;

export default function Button({
    onClick,
    children,
    loading,
    variant = 'default',
    disabled,
    icon: Icon,
}: Props) {
    return <button
        disabled={disabled}
        style={{
            border: '0',
            fontFamily: 'sans-serif',
            padding: '5px 7px',
            borderRadius: 10,
            fontSize: 12,
            cursor: 'pointer',
            ...(disabled ? {
                opacity: 0.5,
                cursor: 'not-allowed',
            } : {}),
            ...(loading ? {
                cursor: 'progress',
            } : {}),
            ...variants[variant],
        }}
        onClick={onClick}
    >
        {Icon ? <div style={{
                display: 'flex',
                flexDirection: 'row',
            }}>
                <Icon style={{paddingRight: 5}}/>
                {children}
            </div> : children}
    </button>
}
