import {isRtlLocale} from '../../../../lib/lang';
import {AttributeFormatContext} from './Format/AttributeFormatContext';
import VisibilityIcon from '@mui/icons-material/Visibility';
import {IconButton} from '@mui/material';
import {getAttributeType} from './types';
import PushPinIcon from '@mui/icons-material/PushPin';
import CopyAttribute from './CopyAttribute';
import React from 'react';
import {attributesClasses} from "./Attributes.tsx";

type Props = {
    type: string;
    definitionId: string;
    locale: string | undefined;
    attributeName: string;
    value: any;
    highlight?: any;
    controls: boolean;
    multiple: boolean;
    togglePin: (definitionId: string) => void;
    pinnedAttributes: string[];
};

export default function AttributeRowUI({
    type,
    definitionId,
    locale,
    attributeName,
    value,
    highlight,
    multiple,
    togglePin,
    pinnedAttributes,
    controls,
}: Props) {
    const isRtl = isRtlLocale(locale);
    const formatContext = React.useContext(AttributeFormatContext);
    const formatter = getAttributeType(type);
    const pinned = pinnedAttributes.includes(definitionId);

    const toggleFormat = React.useCallback<
        React.MouseEventHandler<HTMLButtonElement>
    >(
        e => {
            e.stopPropagation();
            formatContext.toggleFormat(type);
        },
        [formatContext]
    );

    const valueFormatterProps = {
        value,
        highlight,
        locale,
        multiple,
        format: formatContext.formats[type],
    };

    return (
        <div
            style={
                isRtl
                    ? {
                          direction: 'rtl',
                      }
                    : undefined
            }
        >
            <div className={attributesClasses.name}>
                {attributeName}
                {controls ? <div className={attributesClasses.controls}>
                    {formatContext.hasFormats(type) && (
                        <IconButton
                            onClick={toggleFormat}
                        >
                            <VisibilityIcon fontSize={'small'} />
                        </IconButton>
                    )}

                    <CopyAttribute
                        value={formatter.formatValueAsString(
                            valueFormatterProps
                        )}
                    />

                    <IconButton
                        onClick={() => togglePin(definitionId)}
                        sx={{
                            '& svg': {
                                fontSize: 13
                            }
                        }}
                    >
                        <PushPinIcon
                            fontSize={'small'}
                            color={pinned ? 'success' : undefined}
                        />
                    </IconButton>
                </div> : ''}
            </div>
            <div className={attributesClasses.val} lang={locale}>
                {multiple && !formatter.supportsMultiple() ? (
                    <ul className={attributesClasses.list}>
                        {value
                            ? value.map((v: any, i: number) => {
                                  const formatProps = {
                                      value: v,
                                      highlight,
                                      locale,
                                      multiple,
                                      format: formatContext.formats[type],
                                  };

                                  return (
                                      <li key={i}>
                                          {formatter.formatValue(formatProps)}
                                          <CopyAttribute
                                              value={formatter.formatValueAsString(
                                                  formatProps
                                              )}
                                          />
                                      </li>
                                  );
                              })
                            : null}
                    </ul>
                ) : (
                    <>{formatter.formatValue(valueFormatterProps)}</>
                )}
            </div>
        </div>
    );
}
