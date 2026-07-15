import {TreeDefaultState, UseTreeStateReturn} from './types';
import {useEffect, useState} from 'react';

type Props = {
    resolveExpandedNodes?: (selectedNodes: string[]) => Promise<string[]>;
} & TreeDefaultState;

export function useTreeState({
    defaultExpandedNodes = [],
    defaultSelectedNodes = [],
    resolveExpandedNodes,
}: Props): UseTreeStateReturn {
    const [expandedNodes, setExpandedNodes] =
        useState<string[]>(defaultExpandedNodes);
    const [selectedNodes, setSelectedNodes] =
        useState<string[]>(defaultSelectedNodes);

    useEffect(() => {
        if (resolveExpandedNodes && defaultSelectedNodes) {
            resolveExpandedNodes(defaultSelectedNodes).then(expanded => {
                setExpandedNodes(expanded);
            });
        }
    }, []);

    return {
        expandedNodes,
        selectedNodes,
        setExpandedNodes,
        setSelectedNodes,
    };
}
