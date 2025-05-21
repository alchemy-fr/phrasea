import {AttributeFormatterProps, AttributeTypeInstance, AttributeWidgetProps,} from './types';
import React from 'react';
import BaseType from './BaseType.tsx';
import {Workspace} from '../../../../../types.ts';

import {WorkspaceChip} from "../../../../Ui/WorkspaceChip.tsx";
import PrivacyWidget from "../../../../Form/PrivacyWidget.tsx";
import PrivacyChip from "../../../../Ui/PrivacyChip.tsx";
import {Privacy} from "../../../../../api/privacy.ts";

export default class PrivacyType
    extends BaseType
    implements AttributeTypeInstance<Privacy> {
    renderWidget({
        value,
        onChange,
        disabled,
    }: AttributeWidgetProps<Privacy>): React.ReactNode {
        return <PrivacyWidget
            value={value}
            onChange={onChange}
            disabled={disabled}
        />;
    }

    normalize(value: Workspace | undefined): string | undefined {
        return value?.id;
    }

    formatValue({value}: AttributeFormatterProps): React.ReactNode {
        return <PrivacyChip
            privacy={value}
            noAccess={false}
        />
    }

    formatValueAsString({value}: AttributeFormatterProps): string | undefined {
        return value?.nameTranslated;
    }
}
