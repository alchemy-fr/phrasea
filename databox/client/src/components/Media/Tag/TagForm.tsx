import {PureComponent} from "react";
import AppDialog from "../../Layout/AppDialog";
import {Button} from "@mui/material";

type Props = {
    id?: string;
    onSave: () => void;
    onCancel: () => void;
}

export default class TagForm extends PureComponent<Props> {
    render() {
        const isNew = !!this.props.id;

        return <AppDialog
            onClose={this.props.onCancel}
            title={isNew ? 'New tag' : 'Edit tag'}
            actions={({onClose}) => <>
                <Button
                    onClick={onClose}
                    color={'secondary'}
                >
                    Close
                </Button>
            </>}
        >
        {/*    <Form>*/}
        {/*    <Form.Group controlId="include">*/}
        {/*        <Form.Label>Tag name</Form.Label>*/}
        {/*        <Form.Control type={'text'} />*/}
        {/*        <Form.Text className="text-muted">*/}
        {/*            Assets in this collection will only be visible if they contains theses tags.*/}
        {/*        </Form.Text>*/}
        {/*    </Form.Group>*/}
        {/*</Form>*/}
        </AppDialog>
    }
}
