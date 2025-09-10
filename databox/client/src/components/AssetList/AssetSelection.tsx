import React, {useMemo} from 'react';
import {Asset} from '../../types';
import AssetList from './AssetList';
import DisplayProvider from '../Media/DisplayProvider';
import {Layout} from './Layouts';
import {OnSelectionChange} from './types';
import {Trans} from 'react-i18next';

type Props = {
    assets: Asset[];
    onSelectionChange: OnSelectionChange<Asset>;
};

export default function AssetSelection({assets, onSelectionChange}: Props) {
    const pages = useMemo(() => [assets], [assets]);

    return (
        <DisplayProvider
            defaultState={{
                thumbSize: 100,
                displayAttributes: false,
            }}
        >
            <AssetList
                pages={pages}
                total={assets.length}
                searchBar={false}
                onSelectionChange={onSelectionChange}
                layout={Layout.List}
                noActions={true}
                itemLabel={selectionProps => (
                    <>
                        {selectionProps.selectedCount > 0 ? (
                            <Trans
                                i18nKey={
                                    'asset_selection.x_item_with_selection'
                                }
                                defaults={`<strong>{{selection}} / {{total}}</strong> asset`}
                                tOptions={{
                                    defaultValue_other: `<strong>{{selection}} / {{total}}</strong> assets`,
                                }}
                                count={selectionProps.count}
                                values={selectionProps.values}
                            />
                        ) : (
                            <Trans
                                i18nKey={'asset_selection.x_item'}
                                defaults={`<strong>{{count}}</strong> asset`}
                                tOptions={{
                                    defaultValue_other: `<strong>{{total}}</strong> assets`,
                                }}
                                count={selectionProps.count}
                                values={selectionProps.values}
                            />
                        )}
                    </>
                )}
            />
        </DisplayProvider>
    );
}
