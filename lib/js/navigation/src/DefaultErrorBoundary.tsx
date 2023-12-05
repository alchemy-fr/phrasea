import React, {ErrorInfo, PropsWithChildren} from 'react';

export type ErrorFallbackProps = { error: any };
export type TErrorFallbackComponent = (props: ErrorFallbackProps) => React.JSX.Element;

export type TErrorBoundaryComponent = React.JSXElementConstructor<PropsWithChildren<{
    fallback: TErrorFallbackComponent;
}>>;

export default class DefaultErrorBoundary extends React.Component<PropsWithChildren<{
    fallback: TErrorFallbackComponent;
}>, {
    error?: any;
}> {
    state: {
        error?: any;
    } = {};

    static getDerivedStateFromError(error: any) {
        // Update state so the next render will show the fallback UI.
        return {error};
    }

    componentDidCatch(error: Error, errorInfo: ErrorInfo) {
        console.error(error);
        console.debug(errorInfo);
    }

    render() {
        const {error} = this.state;
        if (error) {
            return <>{this.props.fallback({error})}</>
        }

        return <>{this.props.children}</>
    }
}
