import {TreeDefaultState, UseTreeStateReturn} from './types';
import {useState} from 'react';

type Props = {} & TreeDefaultState;

export function useTreeState({
    defaultExpandedNodes = [],
    defaultSelectedNodes = [],
}: Props): UseTreeStateReturn {
    const [expandedNodes, setExpandedNodes] =
        useState<string[]>(defaultExpandedNodes);
    const [selectedNodes, setSelectedNodes] =
        useState<string[]>(defaultSelectedNodes);

    return {
        expandedNodes,
        selectedNodes,
        setExpandedNodes,
        setSelectedNodes,
    };
}
