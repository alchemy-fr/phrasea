FROM golang:1.12.13-alpine3.10

RUN mkdir /usr/app

WORKDIR /usr/app

RUN apk add --no-cache git mercurial

COPY ./src .

RUN go build \
    && chmod +x go.sh \
    && rm *.go *.mod *.sum

EXPOSE 80

CMD ./go.sh
