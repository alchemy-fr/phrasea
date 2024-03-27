import {useMemo} from 'react';
import {Asset} from '../../types';
import AssetList from './AssetList';
import DisplayProvider from '../Media/DisplayProvider';
import {Layout} from './Layouts';
import {OnSelectionChange} from './types';

// function SelectionProxy({pages}: {pages: Asset[][]}) {
//     const {t} = useTranslation();
//     const assetSelection = useContext(AssetSelectionContext);
//
//     const onSelect = useCallback<OnToggle<Asset>(
//         (item, e): void => {
//             e?.preventDefault();
//             assetSelection.setSelection(prev => {
//                 return getItemListFromEvent(prev, item, pages, e);
//             });
//             // eslint-disable-next-line
//         },
//         [pages]
//     );
//
//     const onUnselect = useCallback<OnUnselectAsset>((id, e): void => {
//         e?.preventDefault();
//         assetSelection.setSelection(p => p.filter(i => i !== id));
//         // eslint-disable-next-line
//     }, []);
//
//     return (
//         <div>
//             <FormControlLabel
//                 control={
//                     <Checkbox
//                         checked={
//                             assetSelection.selection.length ===
//                             pages[0].length
//                         }
//                         onChange={(_e, checked) => {
//                             assetSelection.setSelection(
//                                 checked ? pages[0].map(a => a.id) : []
//                             );
//                         }}
//                     />
//                 }
//                 label={`${t(
//                     'form.copy_assets.asset_not_linkable.toggle_select_all',
//                     'Select/Unselect all'
//                 )} (${assetSelection.selection.length}/${
//                     pages[0].length
//                 })`}
//                 labelPlacement="end"
//             />
//             <AssetList
//                 pages={pages}
//                 layout={Layout.List}
//                 selectionContext={assetSelection}
//                 onOpen={}
//             />
//         </div>
//     );
// }

type Props = {
    assets: Asset[];
    onSelectionChange: OnSelectionChange<Asset>;
};

export default function AssetSelection({assets, onSelectionChange}: Props) {
    const pages = useMemo(() => [assets], [assets]);

    return (
        <DisplayProvider thumbSize={100} displayAttributes={false}>
            <AssetList
                pages={pages}
                total={assets.length}
                searchBar={false}
                onSelectionChange={onSelectionChange}
                layout={Layout.List}
                noActions={true}
                itemLabel={'item'}
            />
        </DisplayProvider>
    );
}
