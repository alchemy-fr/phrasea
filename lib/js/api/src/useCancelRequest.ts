import {DependencyList, useCallback, useEffect, useRef} from 'react';
import axios from 'axios';

type UserCancelRequestHandler<P extends Record<string, any>, R = any> = (
    signal: AbortSignal,
    props?: P | undefined
) => Promise<R>;

export default function useCancelRequest<R = any>(
    handler: UserCancelRequestHandler<{}, R>,
    isDevEnv: boolean,
    deps: DependencyList
) {
    const {callback, controller} = useCancelRequestCallback(
        handler,
        isDevEnv,
        deps
    );

    useEffect(() => {
        callback();

        return () => {
            controller.current?.abort();
        };
    }, deps);
}

export function useCancelRequestCallback<
    P extends Record<string, any>,
    R = any,
>(
    handler: UserCancelRequestHandler<P, R>,
    isDevEnv: boolean,
    deps: DependencyList
) {
    const controller = useRef<AbortController | null>(null);

    const callback = useCallback(async (props?: P | undefined) => {
        if (!isDevEnv && controller.current) {
            controller.current!.abort();
        }

        controller.current = new AbortController();

        try {
            return await handler(controller.current!.signal, props);
        } catch (e) {
            if (!axios.isCancel(e)) {
                throw e;
            }
        }
    }, deps);

    return {
        callback,
        controller,
    };
}
