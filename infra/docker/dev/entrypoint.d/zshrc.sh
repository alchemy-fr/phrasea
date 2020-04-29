#!/bin/sh

if [ ! -f "$HOME/.zshrc" ]; then
    cp /bootstrap/.zshrc "$HOME/.zshrc"
fi

if [ ! -d /home/app/.zshrc ]; then
    cp -r /bootstrap/.zshrc /home/app/.zshrc
    chown app:app /home/app/.zshrc
fi
