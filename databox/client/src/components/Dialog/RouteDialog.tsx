import React, {ReactElement, useState} from "react";
import {useLocation, useNavigate} from "react-router-dom";
import {getPath} from "../../routes";

type Props = {
    children(options: {
        open: boolean;
        onClose: () => void;
    }): ReactElement | null;
};

export default function RouteDialog({
                                        children,
                                    }: Props) {
    const {state} = useLocation() as {
        state?: {
            background?: string;
        }
    };
    const [open, setOpen] = useState(true);
    const navigate = useNavigate();

    const onClose = () => {
        setOpen(false);
        navigate(state?.background || getPath('app'));
    }

    return children({
        open,
        onClose,
    });
}
