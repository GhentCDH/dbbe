UGent-Huisstijl/websites changelog
==================================

Version 1.4.2
-------------

- Undo incorrect fix for top nav hor padding (LAF-33).

Version 1.4.1
-------------

- Fix dropdown caret position and top nav hor padding (LAF-33).

Version 1.4.0
-------------

- Add baseline styling (LAF-31).
- Remove rounded corners on alerts (LAF-32).
- Decrease vertical spacing between two top navigation lines (LAF-33).
- Apply italic style to text in disabled buttons (LAF-34).
- Add styling for loading modal (LAF-30).
- Remove lightbox styling from normal modals (LAF-35).
- Add example and styling for application header and footer (LAF-28).
- Add tooltip styling (LAF-36).

Version 1.3.2
-------------

- Update logo Faculty of Law and Criminology (PCM-944).

Version 1.3.1
-------------

- Add hover colors for fa-foursquare and fa-delicious (#26).

Version 1.3.0
-------------

- Merge files after last Fabrique delivery (#23).
- Fix lightbox link border-bottom (#17).
- Fix h3 font size on homepages (#16).
- Fix height of last button in content-text div (Safari) (#24).
- Fix text input in search form on search and error pages (#25).

Version 1.2.8
-------------

- Upgraded to delivery v5.0 of Fabrique (#22).
- Updated static files.

Version 1.2.5
-------------

- Upgraded to delivery v4.0 of Fabrique (#12).

Version 1.2.4
-------------

- Fixed inpage navigation width (#9).
- Remove unnecessary Javascript files from example code (#7).

Version 1.2.3
-------------

- Fixed background color of subhome header on research group homepages (#6).

Version 1.2.2
-------------

- Fixed style of links in unordered lists in subhome header (#3).

Version 1.2.1
-------------

- Fixed dropdown-menu width (#4).

Version 1.2.0
-------------

- Edited style of links in (un)ordered lists (#3).

Version 1.1.2
-------------

- Removed all references to Icomoon font (#2).

Version 1.1.1
-------------

- Fixed logo artifacts in Firefox (#1).
- Added further logo compression (#1).

Version 1.1.0
-------------

- Renamed and updated logo_ugent_en.
- Added logos for faculty of sciences.

Version 1.0.1
-------------

- Completed static directory (moved Panno font files).
- Removed unused iconfont files.

Version 1.0.0
-------------

- First version of UGent-Huisstijl/websites, based on delivery v3.0 of Fabrique. Modifications:
  - Vendor files are managed using [Bower](https://bower.io/) and removed from the repository.
  - The node_modules directory is removed.
  - HTML templates are removed and template generation is removed from the gulpfile.
  - The dist folder is removed, since we will be using git tags to indicate releases.
  - The iconfont font is removed since it is not used, the [Font Awesome](http://fontawesome.io/) font is added instead.
  - Some css fixes and additions, these can be found beneath the `numbers` section in sass/\_settings.scss and in the files included in the `UGent extras` section in sass/screen.scss.
  - The jquery.tocify library is added to enable client-side in-page navigation generation.
  - The gulpfile and sass/screen.scss are modified to reflect above changes.
