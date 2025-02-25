import {Dispatch, PropsWithChildren, SetStateAction} from 'react';
import {Edge, useOnSelectionChange} from 'reactflow';

type Props = PropsWithChildren<{
    setEdges: Dispatch<SetStateAction<Edge[]>>;
}>;

export default function FlowEvents({setEdges, children}: Props) {
    useOnSelectionChange({
        onChange: ({nodes}) => {
            const concernNode = (edge: Edge): boolean => {
                return nodes.some(
                    n => edge.source === n.id || edge.target === n.id
                );
            };

            setEdges(p => {
                return p.map(e => {
                    if (concernNode(e)) {
                        return {
                            ...e,
                            selected: true,
                            animated: true,
                        };
                    } else {
                        return {
                            ...e,
                            selected: false,
                            animated: false,
                        };
                    }
                });
            });
        },
    });

    return <>{children}</>;
}
