import {PureComponent} from "react";
import {Tag} from "../../../types";
import AsyncSelect from "react-select/async";
import {getTags} from "../../../api/tag";

type Props = {
    workspaceId: string;
    value: Tag[],
}

type State = {
    inputValue: string;
}

export default class TagSelector extends PureComponent<Props, State> {
    state: State = {
        inputValue: '',
    };

    handleInputChange = (newValue: string) => {
        const inputValue = newValue.replace(/\W/g, '');
        this.setState({ inputValue });
        return inputValue;
    };

    loadTags = async (inputValue: string) => {
        const data = await getTags({
            query: inputValue,
            workspaceId: this.props.workspaceId,
        });

        return data.map((t: Tag) => ({
            value: t.id,
            label: t.name,
        }));
    }

    render() {
        return (
            <AsyncSelect
                isMulti
                cacheOptions
                defaultOptions
                loadOptions={this.loadTags}
            />
        );
    }
}
