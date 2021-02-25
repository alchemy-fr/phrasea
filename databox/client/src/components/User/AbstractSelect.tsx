import {PureComponent} from "react";
import AsyncSelect from "react-select/async";

export type UserOrGroupOption = {
    label: string;
    value: string;
};

type Props<T> = {
    onSelect: (item: T) => void;
};

type State = {
    inputValue: string;
}

export default abstract class AbstractSelect<T> extends PureComponent<Props<T>, State> {
    state: State = {
        inputValue: '',
    };

    abstract optionToData(option: UserOrGroupOption): T;

    abstract dataToOption(data: T): UserOrGroupOption;

    abstract load(): Promise<T[]>;

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

    onChange = (data: UserOrGroupOption | null): void => {
        if (data) {
            this.props.onSelect(this.optionToData(data));
        }
    }

    render() {
        return <AsyncSelect
            isClearable={true}
            cacheOptions
            defaultOptions
            onChange={this.onChange}
            loadOptions={this.handleLoad}
        />
    }
}
