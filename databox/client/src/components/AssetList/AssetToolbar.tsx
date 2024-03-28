import {LinearProgress, ListSubheader} from '@mui/material';
import {zIndex} from '../../themes/zIndex';
import SearchBar from '../Media/Search/SearchBar';
import SelectionActions, {
    SelectionActionsProps,
} from './Toolbar/SelectionActions';
import {AssetOrAssetContainer} from '../../types';
import assetClasses from './classes';

type Props<Item extends AssetOrAssetContainer> = {
    searchBar?: boolean;
} & SelectionActionsProps<Item>;

export default function AssetToolbar<Item extends AssetOrAssetContainer>({
    loading,
    searchBar = true,
    ...selectionActionsProps
}: Props<Item>) {
    return (
        <>
            <ListSubheader
                className={assetClasses.assetToolbar}
                component="div"
                disableGutters={true}
                sx={() => ({
                    zIndex: zIndex.toolbar,
                })}
            >
                {searchBar ? <SearchBar /> : ''}
                <SelectionActions
                    loading={loading}
                    {...selectionActionsProps}
                />
            </ListSubheader>
            {loading && (
                <div
                    style={{
                        position: 'absolute',
                        left: '0',
                        right: '0',
                        zIndex: 100,
                    }}
                >
                    <LinearProgress />
                </div>
            )}
        </>
    );
}
