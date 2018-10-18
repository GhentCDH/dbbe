#!/bin/bash

rm -r ./web/build/ckeditor
mkdir ./web/build/ckeditor
mkdir ./web/build/ckeditor/lang
cp ./node_modules/ckeditor/lang/en.js ./web/build/ckeditor/lang
cp -R ./node_modules/ckeditor/plugins ./web/build/ckeditor
mkdir ./web/build/ckeditor/skins
cp -R ./node_modules/ckeditor/skins/moono-lisa ./web/build/ckeditor/skins
cp ./node_modules/ckeditor/ckeditor.js ./web/build/ckeditor
cp ./node_modules/ckeditor/config.js ./web/build/ckeditor
cp ./node_modules/ckeditor/contents.css ./web/build/ckeditor
cp ./node_modules/ckeditor/styles.js ./web/build/ckeditor

# remove unnecessary language files
base_path=`pwd`
find web/build/ckeditor -name "lang" -print0 | while IFS= read -r -d $'\0' line; do
    cd "$base_path/$line"
    ls | grep -v "en.js" | xargs rm
done
cd "$base_path"
