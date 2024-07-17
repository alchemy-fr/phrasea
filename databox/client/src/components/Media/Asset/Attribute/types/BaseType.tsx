import {AttributeFormat, AvailableFormat} from './types';

export default abstract class BaseType {
    getAvailableFormats(): AvailableFormat[] {
        return [];
    }

    getDefaultFormat(): AttributeFormat | undefined {
        const availableFormats = this.getAvailableFormats();
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
