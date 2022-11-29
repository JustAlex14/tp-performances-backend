#!/bin/sh
docker build -t $(basename $PWD) .
docker tag $(basename $PWD) arthureudeline/$(basename $PWD)
docker push arthureudeline/$(basename $PWD)