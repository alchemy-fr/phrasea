import {PureComponent} from "react";
import {Tag} from "../../../types";
import AsyncSelect from "react-select/async";
import {getTags} from "../../../api/tag";
import {OptionsType, ValueType} from "react-select";

type TagOption = {
    label: string;
    value: string;
    data: Tag,
};

type Props = {
    workspaceId: string;
    value: Tag[],
}

type State = {
    inputValue: string;
    propsValue: Tag[];
    value: ValueType<TagOption, true>;
}

export default class TagSelect extends PureComponent<Props, State> {
    state: State = {
        inputValue: '',
        value: [],
        propsValue: [],
    };

    handleInputChange = (newValue: string) => {
        const inputValue = newValue.replace(/\W/g, '');
        this.setState({ inputValue });
        return inputValue;
    };

    static getDerivedStateFromProps(props: Props, state: State): Partial<State> | null {
        if (state.propsValue !== props.value) {
            return {
                propsValue: props.value,
                value: props.value.map(t => ({
                    label: t.name,
                    value: t.id,
                    data: t,
                })),
            };
        }

        return null;
    }

    getData(): Tag[] {
        return this.state.value.map(t => t.data);
    }

    loadTags = async (inputValue: string): Promise<ValueType<TagOption, true>> => {
        const data = (await getTags({
            //query: inputValue,
            workspace: this.props.workspaceId,
        })).result;

        return data.map((t: Tag) => ({
            value: t.id,
            label: t.name,
            data: t,
        })).filter(i =>
            i.label.toLowerCase().includes(inputValue.toLowerCase())
        );
    }

    onChange = (data: OptionsType<TagOption>) => {
        this.setState({
            value: data,
        });
    }

    render() {
        return (
            <AsyncSelect
                isMulti
                value={this.state.value}
                cacheOptions
                onChange={this.onChange}
                defaultOptions
                loadOptions={this.loadTags}
            />
        );
    }
}
