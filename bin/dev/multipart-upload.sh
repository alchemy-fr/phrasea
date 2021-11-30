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

splitSize=100
((partSize = $splitSize * 1000000))

fileSize=`wc -c $src | awk '{print $1}'`
((parts = ($fileSize+$partSize-1) / partSize))

tmpDir="/tmp/$RANDOM"

function initUpload() {
  curl --request POST "$baseUrl/uploads" \
    --insecure \
    --silent \
    --header 'Content-Type: application/json' \
    --header "Authorization: Bearer $accessToken" \
    --header 'Accept: application/json' \
    --data-raw "{\"filename\":\"$(basename $src)\",\"type\":\"image/jpeg\",\"size\":$size}" | jq -r '.id'
}

function getPartUrl() {
  curl --request POST "$baseUrl/uploads/$1/part" \
    --insecure \
    --silent \
    --header 'Content-Type: application/json' \
    --header "Authorization: Bearer $accessToken" \
    --header 'Accept: application/json' \
    --data-raw "{\"part\":$2}" | jq -r '.url'
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
  uploadId=`initUpload` \
  && mkdir -p "$tmpDir" \
  && cp "$src" "$tmpDir/" \
  && cd $tmpDir \
  && split -b $partSize $src \
  && rm $src \
  && [ -n "$uploadId" ] \
  && (
    index=0
    jsonData="{\"assetId\":\"$assetId\",\"name\":\"$definitionName\",\"multipart\":{\"uploadId\":\"$uploadId\",\"parts\":["
    for file in *; do
        ((index++))
        uploadUrl=`getPartUrl $uploadId $index`
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
