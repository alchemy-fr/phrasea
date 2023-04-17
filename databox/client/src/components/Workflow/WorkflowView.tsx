import React, {useEffect, useState} from 'react';
import {useParams} from "react-router-dom";
import {getWorkflow} from "../../api/workflow";
import {CircularProgress} from "@mui/material";
import {VisualWorkflow, Workflow} from "@alchemy/visual-workflow";
import "@alchemy/visual-workflow/style.css";
import RouteDialog from "../Dialog/RouteDialog";
import AppDialog from "../Layout/AppDialog";

type Props = {};

const headerHeight = 60;

export default function WorkflowView({}: Props) {
    const {id} = useParams();

    const [data, setData] = useState<Workflow>();

    useEffect(() => {
        getWorkflow(id!).then(c => setData(c));
    }, [id]);

    if (!data) {
        return <CircularProgress/>
    }

    return <RouteDialog>
        {({open, onClose}) => <AppDialog
            open={open}
            disablePadding={true}
            sx={{
                '.MuiDialogTitle-root': {
                    height: headerHeight,
                    maxHeight: headerHeight,
                }
            }}
            fullScreen={true}
            title={<>
                <span>
                <b>{data.name}</b> #{data.id}{' - '}
            </span>
                <span>
                {(data as any).duration}
            </span>
            </>}
            onClose={onClose}
        >
            <div style={{
                width: '100vw',
                height: `calc(100vh - ${headerHeight + 2}px)`,
            }}>
                <VisualWorkflow
                    workflow={data}
                />
            </div>
        </AppDialog>}
    </RouteDialog>
}
