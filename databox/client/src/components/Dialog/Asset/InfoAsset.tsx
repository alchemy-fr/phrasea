import {Asset} from '../../../types';
import {DialogTabProps} from '../Tabbed/TabbedDialog';
import ContentTab from '../Tabbed/ContentTab';
import AssetInfoList from '../../Media/Asset/AssetInfoList.tsx';

type Props = {
    data: Asset;
} & DialogTabProps;

export default function InfoAsset({data, onClose, minHeight}: Props) {
    return (
        <ContentTab onClose={onClose} minHeight={minHeight}>
            <AssetInfoList data={data} />
        </ContentTab>
    );
}
