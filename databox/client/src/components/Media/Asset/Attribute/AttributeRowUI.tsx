import {TAttributeFormatContext} from './Format/AttributeFormatContext';
import VisibilityIcon from '@mui/icons-material/Visibility';
import {IconButton} from '@mui/material';
import {getAttributeType} from './types';
import PushPinIcon from '@mui/icons-material/PushPin';
import CopyAttribute, {copyToClipBoardContainerClass} from './CopyAttribute';
import {useTranslation} from 'react-i18next';
import React from 'react';
import {attributesClasses} from './Attributes';
import {isRtlLocale} from '../../../../lib/lang';
import {Attribute, AttributeDefinitionOrBuiltIn} from '../../../../types.ts';
import GestureIcon from '@mui/icons-material/Gesture';
import {AssetAnnotationRef} from '../Annotations/annotationTypes.ts';
import {
    AttributeFormat,
    AttributeFormatterOptions,
    AttributeFormatterProps,
} from './types/types';
import InvalidAttributeIcon from './InvalidAttributeIcon.tsx';
import {AttributeType} from '../../../../api/types.ts';

export type BaseAttributeRowUIProps = {
    assetAnnotationsRef?: AssetAnnotationRef;
};

type Props = {
    definition: AttributeDefinitionOrBuiltIn;
    attribute: Attribute | Attribute[] | undefined;
    displayControls: boolean;
    togglePin: undefined | ((definition: AttributeDefinitionOrBuiltIn) => void);
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
    const {t, i18n} = useTranslation();
    const {displayName, name, type, multiple, id} = definition;
    const formatter = getAttributeType(type);
    const [overControls, setOverControls] = React.useState(false);

    const toggleFormat = React.useCallback<
        React.MouseEventHandler<HTMLButtonElement>
    >(
        e => {
            e.stopPropagation();
            formatContext.toggleFormat(type, id);
        },
        [formatContext, id]
    );

    const locale = multiple ? undefined : (attribute as Attribute)?.locale;
    const isRtl = locale ? isRtlLocale(locale) : false;

    const formatterOptions: AttributeFormatterOptions = {
        uiLocale: i18n.language,
        t,
    };

    const valueFormatterProps: AttributeFormatterProps = {
        ...formatterOptions,
        value: multiple
            ? ((attribute as Attribute[] | undefined) ?? []).map(a => a.value)
            : (attribute as Attribute)?.value,
        highlight: multiple ? undefined : (attribute as Attribute)?.highlight,
        locale,
        format: format ?? formatContext.getFormat(type, id),
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
                {displayName ?? name}
                {displayControls ? (
                    <div className={attributesClasses.controls}>
                        {overControls ? (
                            <>
                                {!format && formatContext.hasFormats(type) && (
                                    <IconButton
                                        onClick={toggleFormat}
                                        title={formatContext.getFormatTitle(
                                            type,
                                            id
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
                                      ...formatterOptions,
                                      value: a.value,
                                      highlight: a.highlight,
                                      locale: a.locale,
                                      format: formatContext.getFormat(
                                          type,
                                          a.definition.id
                                      ),
                                  };

                                  const isRtl = isRtlLocale(a.locale);

                                  const value = (
                                      a.invalid
                                          ? getAttributeType(AttributeType.Text)
                                          : formatter
                                  ).formatValue(formatProps);
                                  if (undefined === value || null === value) {
                                      return null;
                                  }

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
                                          {value}
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
                                          {Boolean(a.invalid) && (
                                              <InvalidAttributeIcon />
                                          )}
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
                    <>
                        {((attribute as Attribute | undefined)?.invalid
                            ? getAttributeType(AttributeType.Text)
                            : formatter
                        ).formatValue(valueFormatterProps)}
                        {Boolean(
                            (attribute as Attribute | undefined)?.invalid
                        ) && <InvalidAttributeIcon />}
                    </>
                )}
            </div>
        </div>
    );
}
