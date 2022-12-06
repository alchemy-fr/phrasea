#!/bin/sh

if [ ${APP_ENV} == "prod" ];
	then
	./app
else
	go run github.com/pilu/fresh
fi
