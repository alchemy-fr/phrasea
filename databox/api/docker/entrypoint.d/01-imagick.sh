#!/bin/sh

POLICY_FILE="/etc/ImageMagick-${IMAGEMAGICK_POLICY_VERSION}/policy.xml"
PREVIOUS_POLICY_FILE=${POLICY_FILE}.bak

if [ -f "${POLICY_FILE}" ]; then
  if [ ! -f "${PREVIOUS_POLICY_FILE}" ]; then
    echo "Backing up ${POLICY_FILE} to ${PREVIOUS_POLICY_FILE}"
    cp "${POLICY_FILE}" "${PREVIOUS_POLICY_FILE}"
  else
    echo "Restoring ${POLICY_FILE} from ${PREVIOUS_POLICY_FILE}"
    cp "${PREVIOUS_POLICY_FILE}" "${POLICY_FILE}"
  fi

  if [ ! -d $IMAGEMAGICK_POLICY_TEMPORARY_PATH ]; then
    echo "$IMAGEMAGICK_POLICY_TEMPORARY_PATH does not exist lets create it"
    mkdir -p $IMAGEMAGICK_POLICY_TEMPORARY_PATH
  fi

  sed -i -e "s|</policymap>||g" "${POLICY_FILE}"
  if [ -n "${IMAGEMAGICK_POLICY_MEMORY}" ]; then
    echo "  <policy domain=\"resource\" name=\"memory\" value=\"${IMAGEMAGICK_POLICY_MEMORY}\" />" >> "${POLICY_FILE}"
  fi
  if [ -n "${IMAGEMAGICK_POLICY_MAP}" ]; then
    echo "  <policy domain=\"resource\" name=\"map\" value=\"${IMAGEMAGICK_POLICY_MAP}\" />" >> "${POLICY_FILE}"
  fi
  if [ -n "${IMAGEMAGICK_POLICY_WIDTH}" ]; then
    echo "  <policy domain=\"resource\" name=\"width\" value=\"${IMAGEMAGICK_POLICY_WIDTH}\" />" >> "${POLICY_FILE}"
  fi
  if [ -n "${IMAGEMAGICK_POLICY_HEIGHT}" ]; then
    echo "  <policy domain=\"resource\" name=\"height\" value=\"${IMAGEMAGICK_POLICY_HEIGHT}\" />" >> "${POLICY_FILE}"
  fi
  if [ -n "${IMAGEMAGICK_POLICY_DISK}" ]; then
    echo "  <policy domain=\"resource\" name=\"disk\" value=\"${IMAGEMAGICK_POLICY_DISK}\" />" >> "${POLICY_FILE}"
  fi
  if [ -n "${IMAGEMAGICK_POLICY_AREA}" ]; then
    echo "  <policy domain=\"resource\" name=\"area\" value=\"${IMAGEMAGICK_POLICY_AREA}\" />" >> "${POLICY_FILE}"
  fi
  if [ -n "${IMAGEMAGICK_POLICY_TEMPORARY_PATH}" ]; then
    echo "  <policy domain=\"resource\" name=\"temporary-path\" value=\"${IMAGEMAGICK_POLICY_TEMPORARY_PATH}\" />" >> "${POLICY_FILE}"
  fi
  echo "</policymap>" >> "${POLICY_FILE}"
fi
