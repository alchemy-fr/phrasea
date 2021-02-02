import {PureComponent} from "react";
import Modal from "../../Layout/Modal";
import Button from "../../ui/Button";
import apiClient from "../../../api/api-client";
import TagFilterRule, {TagFilterRuleType} from "./TagFilterRule";

type Props = {
    id: string,
    onClose: () => void;
}

type State = {
    rules?: TagFilterRuleType[],
}

export default class EditCollection extends PureComponent<Props, State> {
    state: State = {};

    loadRules = async () => {
        const res: TagFilterRuleType[] = await apiClient.get(`/tags-filter-rules?collection=${this.props.id}`);

        this.setState({
            rules: res,
        });
    }

    componentDidMount() {
        this.loadRules();
    }

    render() {
        const {rules} = this.state;

        return <Modal
            onClose={this.props.onClose}
            header={() => <h4>Edit</h4>}
            footer={({onClose}) => <>
                <Button
                    onClick={onClose}
                    className={'btn-secondary'}
                >
                    Close
                </Button>
                <Button
                    onClick={onClose}
                    className={'btn-primary'}
                >
                    Save changes
                </Button>
            </>}
        >
            {rules && rules.map(r => <TagFilterRule
                {...r}
                key={r.id}
            />)}
        </Modal>
    }
}
