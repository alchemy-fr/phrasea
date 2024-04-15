import React from 'react';

type Props = {
    node: HTMLElement | undefined | null;
    onLoad: () => void;
};

export function useInfiniteScroll({node, onLoad}: Props) {
    React.useEffect(() => {
        if (node) {
            const onScrollEnd = (e: HTMLElementEventMap['scroll']) => {
                const {scrollTop, scrollHeight, clientHeight} =
                    e.currentTarget as HTMLDivElement;
                if (
                    clientHeight < scrollHeight &&
                    scrollTop + clientHeight >= scrollHeight - 20
                ) {
                    onLoad();
                }
            };
            node.addEventListener('scroll', onScrollEnd);

            return () => {
                node.removeEventListener('scroll', onScrollEnd);
            };
        }
    }, [onLoad, node]);
}
