import {
    AttributeFormat,
    AttributeFormatterOptions,
    AvailableFormat,
} from './types';

export default abstract class BaseType {
    getAvailableFormats(
        _options: AttributeFormatterOptions
    ): AvailableFormat[] {
        return [];
    }

    getDefaultFormat(
        options: AttributeFormatterOptions
    ): AttributeFormat | undefined {
        const availableFormats = this.getAvailableFormats(options);
        if (availableFormats.length > 0) {
            return availableFormats[0].name;
        }
    }

    denormalize(value: any): any {
        return value;
    }

    normalize(value: any): any {
        return value;
    }
}
