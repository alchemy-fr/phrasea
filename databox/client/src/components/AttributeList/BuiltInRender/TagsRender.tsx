import {BuiltInRenderProps} from "./builtInRenderTypes.ts";
import AssetTagList from "../../Media/Asset/Widgets/AssetTagList.tsx";

export default function TagsRender({asset}: BuiltInRenderProps) {
    if (!asset.tags || asset.tags.length === 0) {
        return null;
    }

    return <AssetTagList tags={asset.tags}/>
}
