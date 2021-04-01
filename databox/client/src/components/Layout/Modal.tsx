import React, {PureComponent} from "react";
import {Modal as BModal} from 'react-bootstrap';
import {LinearProgress} from "@material-ui/core";

type HeadFootArgs = {
    onClose: () => void,
};

type Props = {
    header?: (args: HeadFootArgs) => React.ReactNode,
    footer?: (args: HeadFootArgs) => React.ReactNode,
    onClose: () => void,
    loading?: boolean,
};

export default class Modal extends PureComponent<Props>
{
    handleClose = () => {
        this.props.onClose();
    }

    render() {
        const {children, footer, header, loading} = this.props;

        return <BModal
            size={'lg'}
            show={true}
            onHide={this.handleClose}
        >
            {loading && <LinearProgress/>}
            {header ? <BModal.Header closeButton>
                <BModal.Title>{header({
                    onClose: this.handleClose,
                })}</BModal.Title>
            </BModal.Header> : ''}
            <BModal.Body>
                {children}
            </BModal.Body>
            {footer ? <BModal.Footer>
                {footer({
                    onClose: this.handleClose,
                })}
            </BModal.Footer> : ''}
        </BModal>
    }
}
