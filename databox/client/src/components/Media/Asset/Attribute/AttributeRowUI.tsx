import {TAttributeFormatContext} from './Format/AttributeFormatContext';
import VisibilityIcon from '@mui/icons-material/Visibility';
import {IconButton} from '@mui/material';
import {getAttributeType} from './types';
import PushPinIcon from '@mui/icons-material/PushPin';
import CopyAttribute, {copyToClipBoardContainerClass} from './CopyAttribute';
import React from 'react';
import {attributesClasses} from './Attributes';
import {isRtlLocale} from '../../../../lib/lang';
import {Attribute, AttributeDefinition} from '../../../../types.ts';
import GestureIcon from '@mui/icons-material/Gesture';
import {AssetAnnotationRef} from '../Annotations/annotationTypes.ts';
import {AttributeFormat} from './types/types';

export type BaseAttributeRowUIProps = {
    assetAnnotationsRef?: AssetAnnotationRef;
};

type Props = {
    definition: AttributeDefinition;
    attribute: Attribute | Attribute[] | undefined;
    displayControls: boolean;
    togglePin: undefined | ((definition: AttributeDefinition) => void);
    pinned: boolean;
    formatContext: TAttributeFormatContext;
    format?: AttributeFormat;
} & BaseAttributeRowUIProps;

export default function AttributeRowUI({
    definition,
    attribute,
    togglePin,
    pinned,
    displayControls,
    formatContext,
    format,
    assetAnnotationsRef,
}: Props) {
    const {nameTranslated, name, fieldType, multiple} = definition;
    const formatter = getAttributeType(fieldType);
    const [overControls, setOverControls] = React.useState(false);

    const toggleFormat = React.useCallback<
        React.MouseEventHandler<HTMLButtonElement>
    >(
        e => {
            e.stopPropagation();
            formatContext.toggleFormat(fieldType, definition.id);
        },
        [formatContext]
    );

    const locale = multiple ? undefined : (attribute as Attribute)?.locale;
    const isRtl = locale ? isRtlLocale(locale) : false;

    const valueFormatterProps = {
        value: multiple
            ? ((attribute as Attribute[] | undefined) ?? []).map(a => a.value)
            : (attribute as Attribute)?.value,
        highlight: multiple ? undefined : (attribute as Attribute)?.highlight,
        locale,
        format: format ?? formatContext.getFormat(fieldType, definition.id),
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
                {nameTranslated ?? name}
                {displayControls ? (
                    <div className={attributesClasses.controls}>
                        {overControls ? (
                            <>
                                {!format &&
                                    formatContext.hasFormats(fieldType) && (
                                        <IconButton
                                            onClick={toggleFormat}
                                            title={formatContext.getFormatTitle(
                                                fieldType,
                                                definition.id
                                            )}
                                        >
                                            <VisibilityIcon />
                                        </IconButton>
                                    )}

                                <CopyAttribute
                                    value={formatter.formatValueAsString(
                                        valueFormatterProps
                                    )}
                                />

                                {togglePin ? (
                                    <IconButton
                                        onClick={() => togglePin(definition)}
                                    >
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
                                      format: formatContext.getFormat(
                                          fieldType,
                                          a.definition.id
                                      ),
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
                                          assetAnnotationsRef?.current &&
                                          a.assetAnnotations ? (
                                              <IconButton
                                                  sx={{
                                                      ml: 1,
                                                  }}
                                                  size="small"
                                                  onClick={e => {
                                                      e.stopPropagation();
                                                      assetAnnotationsRef!.current!.replaceAnnotations(
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
