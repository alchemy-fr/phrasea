import {TAttributeFormatContext} from './Format/AttributeFormatContext';
import VisibilityIcon from '@mui/icons-material/Visibility';
import {IconButton} from '@mui/material';
import {getAttributeType} from './types';
import PushPinIcon from '@mui/icons-material/PushPin';
import CopyAttribute, {copyToClipBoardContainerClass} from './CopyAttribute';
import React from 'react';
import {attributesClasses, OnActiveAnnotations} from './Attributes';
import {isRtlLocale} from '../../../../lib/lang';
import {Attribute, AttributeDefinition} from '../../../../types.ts';
import GestureIcon from '@mui/icons-material/Gesture';

type Props = {
    definition: AttributeDefinition;
    attribute: Attribute | Attribute[];
    displayControls: boolean;
    togglePin: undefined | ((definitionId: string) => void);
    pinned: boolean;
    formatContext: TAttributeFormatContext;
    onActiveAnnotations?: OnActiveAnnotations | undefined;
};

export default function AttributeRowUI({
    definition,
    attribute,
    togglePin,
    pinned,
    displayControls,
    formatContext,
    onActiveAnnotations,
}: Props) {
    const {id, name, fieldType, multiple} = definition;
    const formatter = getAttributeType(fieldType);
    const [overControls, setOverControls] = React.useState(false);

    const toggleFormat = React.useCallback<
        React.MouseEventHandler<HTMLButtonElement>
    >(
        e => {
            e.stopPropagation();
            formatContext.toggleFormat(fieldType);
        },
        [formatContext]
    );

    const locale = multiple ? undefined : (attribute as Attribute).locale;
    const isRtl = locale ? isRtlLocale(locale) : false;

    const valueFormatterProps = {
        value: multiple
            ? (attribute as Attribute[]).map(a => a.value)
            : (attribute as Attribute).value,
        highlight: multiple ? undefined : (attribute as Attribute).highlight,
        locale,
        format: formatContext.formats[fieldType],
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
            onMouseEnter={() => setOverControls(true)}
            onMouseLeave={() => setOverControls(false)}
        >
            <div className={attributesClasses.name}>
                {name}
                {displayControls ? (
                    <div className={attributesClasses.controls}>
                        {overControls ? (
                            <>
                                {formatContext.hasFormats(fieldType) && (
                                    <IconButton onClick={toggleFormat}>
                                        <VisibilityIcon />
                                    </IconButton>
                                )}

                                <CopyAttribute
                                    value={formatter.formatValueAsString(
                                        valueFormatterProps
                                    )}
                                />

                                {togglePin ? (
                                    <IconButton onClick={() => togglePin(id)}>
                                        <PushPinIcon
                                            color={
                                                pinned ? 'success' : undefined
                                            }
                                        />
                                    </IconButton>
                                ) : (
                                    ''
                                )}
                            </>
                        ) : (
                            ''
                        )}
                    </div>
                ) : (
                    ''
                )}
            </div>
            <div className={attributesClasses.val}>
                {multiple ? (
                    <ul className={attributesClasses.list}>
                        {attribute
                            ? (attribute as Attribute[]).map((a, i: number) => {
                                  const formatProps = {
                                      value: a.value,
                                      highlight: a.highlight,
                                      locale: a.locale,
                                      format: formatContext.formats[fieldType],
                                  };

                                  const isRtl = isRtlLocale(a.locale);

                                  return (
                                      <li
                                          key={i}
                                          lang={a.locale}
                                          style={
                                              isRtl
                                                  ? {
                                                        direction: 'rtl',
                                                    }
                                                  : undefined
                                          }
                                          className={
                                              copyToClipBoardContainerClass
                                          }
                                      >
                                          {formatter.formatValue(formatProps)}
                                          {displayControls &&
                                          onActiveAnnotations &&
                                          a.assetAnnotations ? (
                                              <IconButton
                                                  sx={{
                                                      ml: 1,
                                                  }}
                                                  size="small"
                                                  onClick={e => {
                                                      e.stopPropagation();
                                                      onActiveAnnotations!(
                                                          a.assetAnnotations!
                                                      );
                                                  }}
                                              >
                                                  <GestureIcon />
                                              </IconButton>
                                          ) : null}
                                          {displayControls ? (
                                              <CopyAttribute
                                                  value={formatter.formatValueAsString(
                                                      formatProps
                                                  )}
                                              />
                                          ) : (
                                              ''
                                          )}
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
