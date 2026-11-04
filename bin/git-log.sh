#!/bin/bash


git --no-pager log --graph --pretty=format:" %h <b>%s</b> %cn %ad <br/>" -n30 >> ./dashboard/client/git-log.html

# Read by dashboard/client-next (src/features/gitLog/gitLog.ts): hash, date, author, subject
mkdir -p ./dashboard/client-next/build-info
git --no-pager log --pretty=format:"%h%x09%aI%x09%an%x09%s" -n30 > ./dashboard/client-next/build-info/git-log.tsv
