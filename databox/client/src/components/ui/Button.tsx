import {MouseEvent, PureComponent} from "react";

type Size = 'lg' | 'md' | 'sm' | 'xs';

type Props = {
    className?: string;
    disabled?: boolean;
    onClick?: (e: MouseEvent) => void;
    size?: Size,
}

export default class Button extends PureComponent<Props> {
    render() {
        const {
            disabled,
            className,
            children,
            onClick,
            size
        } = this.props;

        return <button
            className={`btn ${className} ${size ? `btn-${size}` : ''}`}
            disabled={disabled}
            onClick={onClick}
        >
            {children}
        </button>
    }
}
