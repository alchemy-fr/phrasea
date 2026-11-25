#!/bin/bash
#
# Create a multipart upload, upload file parts, then create an asset rendition.
#
# Usage:
# export MP_ACCESS_TOKEN='<Your access token here>'
# ./multipart-upload.sh https://databox.phrasea.io /path/to/file.jpg '<assetId>' '<definition name>'
#

set -e

baseUrl="$1"
accessToken="${MP_ACCESS_TOKEN}"
src="$2"
assetId="$3"
definitionName="$4"
size=$(stat --printf="%s" $src)

tmpDir="/tmp/$RANDOM"

# The API decides the part size and returns the presigned URLs of the first parts
function initUpload() {
  curl --request POST "$baseUrl/uploads" \
    --insecure \
    --silent \
    --header 'Content-Type: application/json' \
    --header "Authorization: Bearer $accessToken" \
    --header 'Accept: application/json' \
    --data-raw "{\"filename\":\"$(basename $src)\",\"type\":\"image/jpeg\",\"size\":$size}"
}

# Presigned URLs of the parts from $2, for those the creation did not return
function getPartUrls() {
  curl --request POST "$baseUrl/uploads/$1/parts" \
    --insecure \
    --silent \
    --header 'Content-Type: application/json' \
    --header "Authorization: Bearer $accessToken" \
    --header 'Accept: application/json' \
    --data-raw "{\"from\":$2}"
}

function putPart() {
  curl --request PUT "$1" \
    --insecure \
    --include \
    --silent \
    --data-binary "@$2" | grep 'ETag:' | sed -rn 's/^ETag: "([^"]+)".*/\1/p'
}

function postRendition() {
  curl --request POST "$baseUrl/renditions" \
    --insecure \
    --header 'Content-Type: application/json' \
    --header "Authorization: Bearer $accessToken" \
    --header 'Accept: application/json' \
    --data "@$1"
}

(
  upload=`initUpload` \
  && uploadId=`echo "$upload" | jq -r '.id'` \
  && partSize=`echo "$upload" | jq -r '.chunkSize'` \
  && urls=`echo "$upload" | jq -c '.urls'` \
  && ((parts = ($size+$partSize-1) / partSize)) \
  && mkdir -p "$tmpDir" \
  && cp "$src" "$tmpDir/" \
  && cd $tmpDir \
  && split -a 5 -b $partSize "$(basename $src)" part- \
  && rm "$(basename $src)" \
  && [ -n "$uploadId" ] \
  && (
    index=0
    jsonData="{\"assetId\":\"$assetId\",\"name\":\"$definitionName\",\"multipart\":{\"uploadId\":\"$uploadId\",\"parts\":["
    for file in part-*; do
        ((index++))
        uploadUrl=`echo "$urls" | jq -r --arg n "$index" '.[$n] // empty'`
        if [ -z "$uploadUrl" ]; then
          urls=`getPartUrls $uploadId $index | jq -c '.urls'`
          uploadUrl=`echo "$urls" | jq -r --arg n "$index" '.[$n]'`
        fi
        eTag=`putPart $uploadUrl $file`
        jsonData+="{\"ETag\":\"$eTag\",\"PartNumber\":$index}"
        if (( $index == $parts )); then
          jsonData+="]}}"
        else
          jsonData+=","
        fi
    done
    jq -n $jsonData > fileparts.json
    postRendition fileparts.json
  ) \
  && false
) || rm -r "$tmpDir"
