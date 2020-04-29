#!/bin/sh

if [ ! -d "$HOME/.oh-my-zsh" ]; then
    cp -r "/bootstrap/.oh-my-zsh" "$HOME/.oh-my-zsh"
fi

if [ ! -d /home/app/.oh-my-zsh ]; then
    cp -r "/bootstrap/.oh-my-zsh" /home/app/.oh-my-zsh
    chown -R app:app /home/app/.oh-my-zsh
fi
