#!/bin/bash


# Read by dashboard/client (src/features/gitLog/gitLog.ts): hash, date, author, subject
mkdir -p ./dashboard/client/build-info
git --no-pager log --pretty=format:"%h%x09%aI%x09%an%x09%s" -n30 > ./dashboard/client/build-info/git-log.tsv
