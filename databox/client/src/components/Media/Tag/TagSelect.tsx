import {PureComponent} from "react";
import {Tag} from "../../../types";
import AsyncSelect from "react-select/async";
import {getTags} from "../../../api/tag";
import {OptionsType, ValueType} from "react-select";

type TagOption = {
    label: string;
    value: string;
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
                })),
            };
        }

        return null;
    }

    getData(): Tag[] {
        return this.state.value.map(t => ({
            id: t.value,
            name: t.label,
        }));
    }

    loadTags = async (inputValue: string) => {
        const data = await getTags({
            //query: inputValue,
            workspaceId: this.props.workspaceId,
        });

        return data.map((t: Tag) => ({
            value: t.id,
            label: t.name,
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
