FROM golang:1.19.3-alpine3.17

RUN mkdir /usr/app

WORKDIR /usr/app

RUN apk add --no-cache \
        git \
        mercurial

COPY ./src .

RUN go build -o app . \
    && chmod +x go.sh \
    && rm *.go *.mod *.sum

EXPOSE 80

CMD ["./go.sh"]
