import {useLocation} from '@alchemy/navigation';
import {AppDialog} from "@alchemy/phrasea-ui";
import RouteDialog from "../Dialog/RouteDialog.tsx";
import AttributeEditor from "./AttributeEditor.tsx";
import {useCloseModal} from "../Routing/ModalLink.tsx";
import React from "react";

type Props = {};

export default function AttributeEditorView({}: Props) {
    const {state} = useLocation();
    const closeDrawer = useCloseModal();

    React.useEffect(() => {
        if (!state?.selection) {
            closeDrawer({
                replace: true,
            });
        }
    }, [state]);

    if (!state?.selection) {
        return <></>;
    }

    return (
        <RouteDialog>
            {({open, onClose}) => (
                <AppDialog
                    open={open}
                    disablePadding={true}
                    fullScreen={true}
                    onClose={onClose}
                >
                    <AttributeEditor
                        ids={state.selection}
                    />
                </AppDialog>
            )}
        </RouteDialog>
    );
}
