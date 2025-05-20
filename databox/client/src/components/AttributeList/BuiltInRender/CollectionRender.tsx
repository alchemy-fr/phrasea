import {CollectionChip} from "../../Ui/Chips.tsx";
import {BuiltInRenderProps} from "./builtInRenderTypes.ts";
import assetClasses from "../../AssetList/classes.ts";

export default function CollectionRender({asset}: BuiltInRenderProps) {
    return <div className={assetClasses.collectionList}>
        {asset.collections?.map((collection) => {
            return <CollectionChip key={collection.id}
                label={collection.titleTranslated || collection.title}
            />
        })
        }
    </div>
}
