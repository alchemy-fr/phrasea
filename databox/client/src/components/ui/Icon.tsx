import React, {ComponentType, PureComponent} from "react";

type Props = {
    src?: string;
    component?: ComponentType;
    alt?: string;
    variant?: string;
    className?: string;
};

export default class Icon extends PureComponent<Props> {
    static defaultProps: Props = {
        alt: 'Icon',
        variant: 'sm',
    };

    render() {
        const {
            src,
            alt,
            variant,
            className,
            component,
            ...attrs
        } = this.props;

        if (component) {
            return <span
                {...attrs}
                className={`icon icon-${variant} ${className || ''}`}
            >
                {React.createElement(component)}
            </span>
        }

        return <img
            {...attrs}
            className={`icon icon-${variant}`}
            src={src}
            alt={alt}
        />
    }
}
