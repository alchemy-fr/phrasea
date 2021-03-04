import {PureComponent} from "react";
import AsyncSelect from "react-select/async";
import {ActionMeta, OptionsType, ValueType} from "react-select";

export type UserOrGroupOption = {
    label: string;
    value: string;
};

type Props<T> = {
    onSelect?: (item: T) => void;
};

type State = {
    inputValue: string;
    value: ValueType<UserOrGroupOption, false>;
}

export default abstract class AbstractSelect<T> extends PureComponent<Props<T>, State> {
    state: State = {
        inputValue: '',
        value: null,
    };

    abstract optionToData(option: UserOrGroupOption): T;

    abstract dataToOption(data: T): UserOrGroupOption;

    abstract load(): Promise<T[]>;

    abstract getType(): string;

    handleInputChange = (newValue: string) => {
        const inputValue = newValue.replace(/\W/g, '');
        this.setState({inputValue});
        return inputValue;
    };

    handleLoad = async (inputValue: string): Promise<UserOrGroupOption[]> => {
        const data = await this.load();

        return data.map(this.dataToOption)
            .filter(i => i.label.toLowerCase().includes(inputValue.toLowerCase()));
    }

    onChange = (data: ValueType<UserOrGroupOption, false>): void => {
        this.setState({value: data}, () => {
            if (data) {
                const {onSelect} = this.props;
                onSelect && onSelect(this.optionToData(data));
            }
        });
    }

    getValue() {
        return this.state.value ? this.state.value.value : null;
    }

    render() {
        return <AsyncSelect
            cacheOptions
            defaultOptions
            placeholder={`Select a ${this.getType()}`}
            onChange={this.onChange}
            loadOptions={this.handleLoad}
            value={this.state.value}
        />
    }
}
