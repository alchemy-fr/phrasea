import {WorkspaceChip} from "../../Ui/Chips.tsx";
import {BuiltInRenderProps} from "./builtInRenderTypes.ts";

export default function WorkspaceRender({asset}: BuiltInRenderProps) {
    const workspace = asset.workspace;

    return <WorkspaceChip
        label={workspace.nameTranslated || workspace.name}
        size={'small'}
    />
}
