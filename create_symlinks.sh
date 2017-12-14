#!/bin/bash

mkdir -p ./web/build/static/icons
ln -rs ./assets/websites/static/icons/* ./web/build/static/icons

mkdir -p ./web/build/static/images
ln -rs ./assets/websites/static/images/* ./web/build/static/images
