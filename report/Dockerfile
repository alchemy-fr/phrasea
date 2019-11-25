FROM golang:1.12.13-alpine3.10

RUN mkdir /usr/app

WORKDIR /usr/app

RUN apk add --no-cache git mercurial

COPY ./src .

RUN go mod init /usr/app \
    && go get \
    && go build \
    && chmod +x go.sh

CMD ./go.sh

EXPOSE 80
