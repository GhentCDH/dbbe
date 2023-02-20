# UGent

Static front-end templates for the UGent website based on [Bootstrap v3.3.6](http://getbootstrap.com).

## Usage

All files that are needed to apply the new styling of UGent websites are located in the [static](static) directory. If no further development is required, it suffices to copy this directory and include the files as demonstrated in [example.html](example.html).

## Development

### Requirements

- [Node 0.12+](https://nodejs.org/en/)

### Installing

#### Npm dependencies

Use npm to install the dependencies by running `$ npm install` in the project root.

#### Bower

If you don't have a global version of Bower yet also run `$ (sudo) npm install bower --global`. This allows you to run the `$ bower install` command which is required to download third party libraries needed to build the project.

#### Gulp

If you don't have a global version of Gulp yet also run `$ (sudo) npm install gulp gulp-cli --global`. This allows you to run the `$ gulp`
command which is required to build the project.

### Building

To build the project the following steps need to be done:

- `$ bower install` to download third libraries (only needed on the first run and when a dependency has been added, modified or removed)
- `$ gulp` to clean the `static` directory and process the JavaScript, font and CSS files.
- `$ gulp watch` to trigger a rebuild when any of the JavaScript or sass source files change. It is recommended to run `$ gulp` once before this command.

The result is in the `static` directory.

### Developing
CSS is compiled from SCSS which lives in the [sass](sass) folder.

Like Bootstrap, certain styles can be achieved by adding custom classes. See [\_helpers.scss](sass/base/_helpers) to view the currently available class names.

#### Icons
Icons are a collection of custom icons, Bootstrap-Glyphicons and Font Awesome.
New icons can be added with Font Awesome since the complete collection is loaded into the pages: http://fontawesome.io/cheatsheet/

#### Features

##### Responsive images
Images are used responsively with [Picturefill](https://github.com/scottjehl/picturefill).
If this is not possible (due to restrictions of the CMS for instance) you can upload only the largest version of an image which will be shown on all devices.

##### Lightbox
Images and videos can be opened in a lightbox. You can read about it here:
http://ashleydw.github.io/lightbox/

##### Autosuggest
The search in the content uses autosuggest made by Typeahead:
https://github.com/twitter/typeahead.js

For demo-purpose the source is inside the main.js.
For production the sourcefile can be a json-file.

##### Table of contents
There are two ways to present a table of contents:
- If the table of contents is generated at the server side, the smooth scrolling effect demonstrated on the [inpage navigation template](https://styleguide.ugent.be/dist/generic_inpage_nav_left.html) can be obtained as described on the [smooth-scroll readme page](https://github.com/cferdinandi/smooth-scroll). The initialization for this library is included in [main.js](js/main.js).
- If the table of contents is not generated at the server side, it can be added by using the [jquery.tocify.js library](http://gregfranko.com/jquery.tocify.js/). Smooth scrolling is automatically included in this library.

In both cases, the table of contents can be fixed in position by using the `data-lockfixed` attribute, as demonstrated on the [inpage navigation template](https://styleguide.ugent.be/dist/generic_inpage_nav_left.html). The JavaScript code to enable this effect is present in [main.js](js/main.js).
