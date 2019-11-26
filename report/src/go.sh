#!/bin/sh

if [ ${APP_ENV} == "production" ];
	then
	app
else
	go get github.com/pilu/fresh \
	&& fresh
fi
