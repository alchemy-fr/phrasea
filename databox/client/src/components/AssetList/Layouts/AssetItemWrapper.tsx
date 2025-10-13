import {AssetItemProps} from '../types';
import {AssetOrAssetContainer} from '../../../types';
import assetClasses from '../classes';
import React, {PropsWithChildren} from 'react';
import classNames from 'classnames';

export default function AssetItemWrapper<Item extends AssetOrAssetContainer>({
    itemComponent,
    onToggle,
    item,
    children,
    selected,
    disabled,
}: PropsWithChildren<
    Pick<
        AssetItemProps<Item>,
        'item' | 'itemComponent' | 'onToggle' | 'selected' | 'disabled'
    >
>) {
    const node = (
        <div
            onMouseDown={e => onToggle(item, e)}
            className={classNames({
                [assetClasses.item]: true,
                disabled,
                selected,
            })}
        >
            {children}
        </div>
    );

    return itemComponent
        ? React.createElement(itemComponent, {
              item,
              children: node,
          })
        : node;
}
