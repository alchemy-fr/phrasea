#!/bin/bash


git --no-pager log --graph --pretty=format:" %h <b>%s</b> %cn %ad <br/>" -n30 >> ./dashboard/client/git-log.html

