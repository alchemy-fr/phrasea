import {MouseEvent, PureComponent} from "react";

type Size = 'lg' | 'md' | 'sm' | 'xs';

type Props = {
    className?: string;
    disabled?: boolean;
    onClick?: (e: MouseEvent) => void;
    size?: Size,
    type?: 'submit' | 'reset' | 'button';
}

export default class Button extends PureComponent<Props> {
    render() {
        const {
            disabled,
            className,
            children,
            onClick,
            size,
            type,
        } = this.props;

        return <button
            className={`btn ${className || ''} ${size ? `btn-${size}` : ''}`}
            disabled={disabled}
            onClick={onClick}
            type={type || 'button'}
        >
            {children}
        </button>
    }
}
