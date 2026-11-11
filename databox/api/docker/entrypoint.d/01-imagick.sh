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

  # Deny by default: uploaded files are untrusted and ImageMagick picks the
  # coder from their magic bytes, whatever their extension or declared type.
  # The last matching policy wins, so the allow list comes after "none *".
  # PNM reads back the pages Ghostscript renders in CMYK (PAM) from a PDF.
  # Ghostscript is linked as a library (gslib): PDF needs no delegate.
  cat >> "${POLICY_FILE}" <<'EOF'
  <policy domain="delegate" rights="none" pattern="*" />
  <policy domain="module" rights="none" pattern="*" />
  <policy domain="module" rights="read|write" pattern="{JPEG,PNG,GIF,WEBP,TIFF,HEIC,PSD,BMP,ICON,PDF,PNM}" />
  <policy domain="coder" rights="none" pattern="{PS,PS2,PS3,EPS,EPSI,EPSF,EPI,EPT,EPT2,EPT3,XPS,PCL,SVG,SVGZ,MSVG,RSVG,MVG,MSL,TEXT,TXT,LABEL,CAPTION,PANGO,EPHEMERAL,URL,HTTP,HTTPS,FTP,FILE,SHOW,WIN,PLT}" />
  <policy domain="path" rights="none" pattern="@*" />
EOF
  echo "</policymap>" >> "${POLICY_FILE}"
fi
