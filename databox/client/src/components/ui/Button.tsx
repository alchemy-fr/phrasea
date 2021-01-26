import {MouseEvent, PureComponent} from "react";

type Props = {
    className?: string;
    disabled?: boolean;
    onClick?: (e: MouseEvent) => void;
}

export default class Button extends PureComponent<Props> {
    render() {
        const {
            disabled,
            className,
            children,
            onClick,
        } = this.props;

        return <button
            className={`btn ${className}`}
            disabled={disabled}
            onClick={onClick}
        >
            {children}
        </button>
    }
}
