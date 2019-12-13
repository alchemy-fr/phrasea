#!/bin/sh

if [ ${APP_ENV} == "prod" ];
	then
	./app
else
	go get github.com/pilu/fresh \
	&& fresh
fi
