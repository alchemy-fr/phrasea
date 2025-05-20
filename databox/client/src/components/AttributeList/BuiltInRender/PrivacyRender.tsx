import {BuiltInRenderProps} from "./builtInRenderTypes.ts";
import PrivacyChip from "../../Ui/PrivacyChip.tsx";

export default function PrivacyRender({asset}: BuiltInRenderProps) {
    return <PrivacyChip
        privacy={asset.privacy}
        size={'small'}
        noAccess={!asset.workspace}
    />
}
