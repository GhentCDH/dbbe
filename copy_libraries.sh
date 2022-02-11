#!/bin/bash

mkdir -p ./public/build/ckeditor/lang
cp ./node_modules/ckeditor/lang/en.js ./public/build/ckeditor/lang
cp -R ./node_modules/ckeditor/plugins ./public/build/ckeditor
mkdir -p ./public/build/ckeditor/skins
cp -R ./node_modules/ckeditor/skins/moono-lisa ./public/build/ckeditor/skins
cp ./node_modules/ckeditor/ckeditor.js ./public/build/ckeditor
cp ./node_modules/ckeditor/config.js ./public/build/ckeditor
cp ./node_modules/ckeditor/contents.css ./public/build/ckeditor
cp ./node_modules/ckeditor/styles.js ./public/build/ckeditor

# remove unnecessary language files
find public/build/ckeditor -name "lang" -print0 | while IFS= read -r -d $'\0' line; do
    ls -d -1 "$line/"*.* | grep -v "en.js" | xargs -r rm
done
