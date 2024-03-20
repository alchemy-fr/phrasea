// @ts-nocheck
import {
    PropsWithChildren,
    useCallback,
    useContext,
    useEffect,
    useRef,
    useState,
} from 'react';
import {AssetSelectionContext} from '../../context/AssetSelectionContext.tsx';
import {ResultContext} from './Search/ResultContext';
import {StateSetterArg} from '../../types/react';

type Props = PropsWithChildren<{
    onSelectionChange?: (selectedAssets: string[]) => void;
}>;

export default function AssetSelectionProvider({
    children,
    onSelectionChange,
}: Props) {
    const resultContext = useContext(ResultContext);
    const [selectedAssets, setSelectedAssets] = useState<string[]>([]);
    const didMount = useRef(false);

    const setSelectedAssetsProxy = useCallback(
        (selection: StateSetterArg<string[]>) => {
            if (typeof selection === 'function') {
                setSelectedAssets(p => {
                    const next = selection(p);
                    onSelectionChange && onSelectionChange(p);

                    return next;
                });
            } else {
                setSelectedAssets(selection);
                onSelectionChange && onSelectionChange(selection);
            }
        },
        []
    );

    useEffect(() => {
        if (didMount.current) {
            setSelectedAssetsProxy([]);
        } else {
            didMount.current = true;
        }
    }, [resultContext.pages[0]]);

    return (
        <AssetSelectionContext.Provider
            value={{
                selection: selectedAssets,
                select: setSelectedAssetsProxy,
            }}
        >
            {children}
        </AssetSelectionContext.Provider>
    );
}
