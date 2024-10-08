import {ActionsContext} from './types.ts';
import {AssetOrAssetContainer} from '../../types.ts';

export function createDefaultActionsContext<
    Item extends AssetOrAssetContainer,
>(): ActionsContext<Item> {
    return {
        basket: true,
        layout: true,
        export: true,
        edit: true,
        share: true,
        delete: true,
        open: true,
        move: true,
        copy: true,
        replace: true,
    };
}
