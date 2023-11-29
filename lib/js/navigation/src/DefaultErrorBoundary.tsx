import React, {PropsWithChildren} from 'react';

export type ErrorFallbackComponent = (props: { error: any }) => JSX.Element;

export default class DefaultErrorBoundary extends React.Component<PropsWithChildren<{
    fallback: ErrorFallbackComponent;
}>, {
    error?: any;
}> {
    static getDerivedStateFromError(error) {
        // Update state so the next render will show the fallback UI.
        return {error};
    }

    componentDidCatch(error, errorInfo) {
    }

    render() {
        const {error} = this.state;
        if (error) {
            return this.props.fallback({error})
        }

        return this.props.children;
    }
}
