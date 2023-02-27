import React from 'react';
import {AttributeFormatterProps, AttributeTypeInstance, AttributeWidgetProps} from "./types";
import BaseType from "./BaseType";
import TagNode from "../../../../Ui/TagNode";

export default class TagType extends BaseType implements AttributeTypeInstance {
    renderWidget(props: AttributeWidgetProps): React.ReactNode {
        return <></>
    }

    formatValue({value}: AttributeFormatterProps): React.ReactNode {
        return <TagNode
            name={value.name}
            color={value.color}
        />
    }

    formatValueAsString({value}: AttributeFormatterProps): string | undefined {
        return value.name;
    }

    supportsMultiple(): boolean {
        return true;
    }
}
