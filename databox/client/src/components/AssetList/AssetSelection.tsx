import {useMemo} from 'react';
import {Asset} from '../../types';
import AssetList from './AssetList';
import DisplayProvider from '../Media/DisplayProvider';
import {Layout} from './Layouts';
import {OnSelectionChange} from './types';

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
