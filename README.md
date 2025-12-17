# JetSearch For Elementor

The best tool for adding complex search functionality to pages built with Elementor.

# ChangeLog

# 3.5.16.1
* FIX: WP Security Standards.

# 3.5.16
* ADD: Current JetSearch Results macros for JetEngine Query Builder integration;
* ADD: Bulk delete support for Suggestions;
* FIX: Show All Results Button ignores toggle state in Bricks;
* FIX: improve accessibility for Ajax Search and Search Suggestions widgets.

# 3.5.15
* FIX: display all terms and posts in Ajax Search widget for Bricks editor;
* FIX: add post_type parameter to AJAX request in block editor to load terms based on selected source in Ajax Search widget;
* FIX: prevent double initialization of JetForm inside tabs;

# 3.5.14
* ADD: Weglot compatibility for custom search results redirect;
* FIX: add validation for include/exclude term IDs in settings;
* FIX: recalculate results list height on resize and orientation change to prevent hidden results;
* FIX: pause Slick slider autoplay during search typing, resume after leaving input field;
* FIX: sync validation between Custom Search Results URL and Search Query Param in default Ajax Search settings.

# 3.5.13
* FIX: prevent fatal error on incorrect JSON in Jet_Search_Ajax_Handlers;
* FIX: replace _stock_status meta_query with product_visibility taxonomy to improve query performance;
* FIX: show custom attributes in “Taxonomies” selector ( global settings ).

# 3.5.12
* FIX: WP Security Standards.

# 3.5.11
* ADD: jet-search/tools/get-post-types filter to modify the CPT list;
* UPD: jet-search/ajax-search/thumbnail-html and jet-search/ajax-search/thumbnail-placeholder-html hooks;
* FIX: apply escaping to dynamic classes and attributes for proper HTML output;
* FIX: minor issues.

# 3.5.10.1
* ADD: sanitize user input and escape output to prevent potential XSS in Search Suggestions widget;
* FIX: sanitize and typecast term and post ID filters as arrays in Ajax Search Tax Query.

# 3.5.10
* ADD: trigger 'jet-ajax-search/show-results/listing' for compatibility;
* UPD: support 'jet-search/ajax-search/query-args' filter on Results and Custom Results pages;
* FIX: apply focus to search field when popup entrance animation is active.

# 3.5.9
* ADD: `Target Widget ID ( optional )` compatibility for Archive Templates;
* ADD: support for custom attributes in 'Search in taxonomy terms' for Bricks and Gutenberg;
* ADD: trigger 'added_to_cart' event for plugins compatibility.

# 3.5.8
* ADD: filters to allow including custom attributes in the Ajax Search widget;
* ADD: `Target Widget ID ( optional )` option to limit search results to specific Listing Grid on custom results page;
* UPD: package.json;
* UPD: language files;
* FIX: exclude `accordion.default` widget from initialization;
* FIX: handle wide space characters in Ajax Search widget.

# 3.5.7.1
* FIX: Sanitize and validate input data to prevent XSS in results-area;

# 3.5.7
* FIX: Bricks. Convert `symbols_for_start_searching` option to an integer in the Ajax Search widget;
* FIX: Filter out empty values in ajaxSendData before sending request in the Ajax Search widget;
* UPD: Compatibility with Elementor 3.26.

# 3.5.6
* ADD: 'X-WP-Nonce' header for REST API requests in Ajax Search widget;
* ADD: excluded the GallerySlider widget from initialization in JetSearch;
* UPD: Compatibility with Elementor 3.24;
* FIX: dynamic content handling in popups for listing templates in Ajax Search widget;
* FIX: SQL condition handling for Ajax Search widget.

# 3.5.5.1
* FIX: Compatibility with JetSmartFilters 3.6.0.

# 3.5.5
* FIX: Bricks builder Listing templates styles;
* FIX: Issues with preview templates and options in the Blocks editor;
* FIX: Compatibility with Elementor 3.26;
* ADD: Added compatibility with Custom Meta Storage
* FIX: Minor issues.

# 3.5.4
* ADD: Add suggestions after selecting a post from the results area in AJAX search;
* FIX: Fix product variations appearing in search results when the product is hidden from search;
* FIX: TranslatePress compatibility;
* FIX: minor issues.

# 3.5.3
* ADD: The `jet-search/custom-url-handler/allow-merge-queries-post-types` filter;
* ADD: The `Request type` setting allows switching the search request type between REST API and AJAX;
* ADD: The `Maximum Word Length for Suggestion Item Titles` option allows trimming suggestion titles by a specified number of words;
* FIX: Resolved an issue where suggestions with special characters were duplicated;
* ADD: `jet-search/ajax-search/before-search-sources` hook;
* FIX: minor issues.

# 3.5.2.1
* FIX: security issue.

# 3.5.2
* ADD: [Crocoblock/suggestions#7640](https://github.com/Crocoblock/suggestions/issues/7640);
* ADD: `jet-ajax-search/assets/localize-data/use-legacy-ajax` filter to determine whether to use the legacy AJAX method or REST API for search functionality.

# 3.5.1
* FIX: Better sanitize parameters before use in DB queries.

# 3.5.0
* ADD: [Crocoblock/suggestions#6938](https://github.com/Crocoblock/suggestions/issues/6938);
* ADD: [Crocoblock/suggestions#6896](https://github.com/Crocoblock/suggestions/issues/6896);
* ADD: `jet-search/ajax-search/search-by-post-id` filter to add the ability to search by post ID;
* ADD: `Additional Results` section with terms and user search sources;
* FIX: Better TranslatePress compatibility;

# 3.4.4
* ADD: `jet-search/ajax-search/show-product-variations` filter to show/hide product variations from the search result;
* UPD: JetDashboard to v2.2.0;
* FIX: minor issues.

# 3.4.3
* FIX: Exclude Terms option in the Ajax Search widget;
* FIX: URL params duplication in the Ajax Search widget;
* FIX: minor issues.

# 3.4.2
* FIX: improved compatibility with JetSmartFilters in the Ajax Search widget;
* FIX: search functionality with custom fields in the Ajax Search widget;
* FIX: minor issues.

# 3.4.1
* FIX: security issue;
* FIX: minor issues.

# 3.4.0
* ADD: [Crocoblock/suggestions#6926](https://github.com/Crocoblock/suggestions/issues/6926);
* ADD: [Crocoblock/suggestions#2559](https://github.com/Crocoblock/suggestions/issues/2559);
* ADD: Ability to display search suggestions or user search history in the Ajax Search widget;
* FIX: minor issues.

# 3.3.2
* FIX: Better WPML compatibility
* ADD: option to disable saving search suggestions via JetSearch widgets/blocks.
* FIX: minor issues.

# 3.3.1
* FIX: select searched category in the search category dropdown on the search results page;
* FIX: SmartFilters compatibility with the `Search in taxonomy terms` option.

# 3.3.0
* ADD: Settings page for Ajax Search widget, enabling customization of default Ajax Search settings;
* ADD: `Search Query Save to Search Suggestions` control in the Ajax Search widget;
* ADD: `Disable Submit on Enter` control in the Ajax Search widget;
* ADD: `jet-search/query/set-search-query` hook, allow to filter search query;
* ADD: `Custom Search Results URL` control in the Ajax Search widget for page link where search results will be displayed;
* ADD: Allow specific tags for Notifications;
* FIX: minor issues.

# 3.2.3
* FIX: Blocks editor `Divider` and `Enable Scrolling` styles.

# 3.2.2
* FIX: Resolved Ajax Search widget issue within Jet Popup during ajax loading;
* FIX: Resolved the issue with the `Highlight Searched Text` option in the block editor when the option was turned off;
* FIX: Minor bug fixes.

# 3.2.1
* FIX: accessibility in the widgets.

# 3.2.0.1
* ADD: Check for the presence of sessions table in the database;
* ADD: Disable token clearing when the session usage option is turned off.

# 3.2.0
* ADD: Bricks Builder сompatibility;
* ADD: Added a new validation mechanism for adding new suggestions via the Search Suggestions widget;
* FIX: rest api urls;
* FIX: Search in taxonomy terms issue;
* FIX: minor issues.

# 3.1.3.1
* FIX: js issue.

# 3.1.3
* FIX: compatibility with Polylang/WPML;
* FIX: Fixed the issue for searching by category and terms;
* FIX: security issue;
* FIX: minor issues.

# 3.1.2.1
* FIX: security issue.

# 3.1.2
* FIX: Ajax Search blocks issue with custom fields;
* ADD: `Session usage settings` setting for Suggestions to resolve caching issues
* ADD: `jet-ajax-search/form/post-types` filter hook
* FIX: Ajax Search incorrect notifications issue
* UPD: JetDashboard Module to v2.1.4

# 3.1.1
* FIX: [Crocoblock/suggestions#6933](https://github.com/Crocoblock/suggestions/issues/6933);
* FIX: Include / Exclude terms issue
* ADD: `Is Products Search` option in the Search Suggestions Widget
* FIX: minor issues

# 3.1.0
* ADD: Search suggestions widget;
* ADD: Search suggestions admin UI;
* FIX: Better sanitizeing custom callbacks before execute;
* FIX: Showing results by post type;
* FIX: Markup issue with enabled highlight;
* FIX: Search with products archive.

# 3.0.3
* ADD: `jet-search/ajax-search/query-args` filter hook
* ADD: `jet-search/template/pre-get-content` filter hook
* ADD: `jet-search/template/pre-get-meta-field` filter hook
* ADD: `Minimal Quantity of Symbols for Search` option
* ADD: `jet-ajax-search/show-results` trigger on search AJAX request success
* FIX: minor issues

# 3.0.2
* ADD: [Crocoblock/suggestions#5712](https://github.com/Crocoblock/suggestions/issues/5712);
* ADD: [Crocoblock/suggestions#5742](https://github.com/Crocoblock/suggestions/issues/5742);
* FIX: issues with the `Search in taxonomy terms` option;
* FIX: compatibility with Elementor 3.7.
* FIX: minor issues

# 3.0.1
* UPD: Allow to disable submitting the search form on Enter click.

## 3.0.0
* ADD: Blocks Editor integration;
* ADD: Allow to search in taxonomy terms (include into results posts wich has terms with search query);
* ADD: Crocoblock/suggestions#4631;
* ADD: Allow to highlight search query in the search results;
* FIX: Navigation Arrows in Ajax Search withg Blocksy theme;
* FIX: Deprecated notice for Elementor editor;
* FIX: Items are duplicated in listing grid on search result page.

## 2.2.0 - 14.06.2022
* ADD: Blocks Editor integration;
* ADD: Allow to search in taxonomy terms (include into results posts wich has terms with search query);
* ADD: [Crocoblock/suggestions#4631](https://github.com/Crocoblock/suggestions/issues/4631);
* ADD: Allow to highlight search query in the search results;
* FIX: Navigation Arrows in Ajax Search withg Blocksy theme;
* FIX: Deprecated notice for Elementor editor;
* FIX: Items are duplicated in listing grid on search result page.

## [2.1.17](https://github.com/ZemezLab/jet-search/releases/tag/2.1.17) - 14.04.2022
* Added: [Crocoblock/suggestions#5090](https://github.com/Crocoblock/suggestions/issues/5090)
* Added: [Crocoblock/suggestions#4886](https://github.com/Crocoblock/suggestions/issues/4886)

## [2.1.16](https://github.com/ZemezLab/jet-search/releases/tag/2.1.16) - 23.03.2022
* Fixed: elementor 3.6 compatibility

## [2.1.15](https://github.com/ZemezLab/jet-search/releases/tag/2.1.15) - 24.12.2021
* Added: [Crocoblock/suggestions#3034](https://github.com/Crocoblock/suggestions/issues/3034)
* Fixed: minor issues

## [2.1.14](https://github.com/ZemezLab/jet-search/releases/tag/2.1.14) - 30.07.2021
* Fixed: compatibility with JetMenu on search result page

## [2.1.13](https://github.com/ZemezLab/jet-search/releases/tag/2.1.13) - 27.07.2021
* Added: better compatibility with JetSmartFilters
* Added: better compatibility with JetEngine
* Added: better compatibility with Polylang
* Fixed: showing search result on products search result page

## [2.1.12](https://github.com/ZemezLab/jet-search/releases/tag/2.1.12) - 17.06.2021
* Fixed: prevent php notice

## [2.1.11](https://github.com/ZemezLab/jet-search/releases/tag/2.1.11) - 28.04.2021
* Fixed: prevent php notice

## [2.1.10](https://github.com/ZemezLab/jet-search/releases/tag/2.1.10) - 22.04.2021
* Added: better compatibility with JetEngine
* Added: Elementor compatibility tag
* Added: [Crocoblock/suggestions#1611](https://github.com/Crocoblock/suggestions/issues/1611)
* Added: multiple improvements
* Updated: JetDashboard Module to v2.0.8
* Fixed: Various issue

## [2.1.9](https://github.com/ZemezLab/jet-search/releases/tag/2.1.9) - 13.11.2020
* Added: multiple improvements
* Updated: JetDashboard Module to v2.0.4
* Fixed: init session

## [2.1.8](https://github.com/ZemezLab/jet-search/releases/tag/2.1.8) - 01.09.2020
* Added: better compatibility with JetSmartFilters on the search result page

## [2.1.7](https://github.com/ZemezLab/jet-search/releases/tag/2.1.7) - 27.07.2020
* Added: multiple improvements
* Update: JetDashboard Module to v1.1.0
* Fixed: search by the current query

## [2.1.6](https://github.com/ZemezLab/jet-search/releases/tag/2.1.6) - 13.05.2020
* Added: `jet-search/get-locate-template` filter hook
* Added: multiple improvements and bug fixes

## [2.1.5](https://github.com/ZemezLab/jet-search/releases/tag/2.1.5) - 19.03.2020
* Added: `Serach by the current query` option
* Added: `Sentence Search` option
* Added: `Thumbnail Placeholder` option
* Added: multiple improvements and bug fixes
* Updated: optimized script dependencies

## [2.1.4](https://github.com/ZemezLab/jet-search/releases/tag/2.1.4) - 12.03.2020
* Added: support for Font Awesome 5 and SVG icons
* Added: multiple improvements

## [2.1.3](https://github.com/ZemezLab/jet-search/releases/tag/2.1.3) - 24.02.2020
* Added: better compatibility with WooCommerce Multilingual plugin
* Added: multiple improvements

## [2.1.2](https://github.com/ZemezLab/jet-search/releases/tag/2.1.2) - 21.02.2020
* Update: Jet-Dashboard Module to v1.0.10
* Added: multiple improvements
* Fixed: compatibility with Elementor 2.9

## [2.1.1](https://github.com/ZemezLab/jet-search/releases/tag/2.1.1) - 15.01.2020
* Update: Jet-Dashboard Module to v1.0.9
* Added: multiple improvements

## [2.1.0](https://github.com/ZemezLab/jet-search/releases/tag/2.1.0) - 02.12.2019
* Added: Jet Dashboard

## [2.0.2](https://github.com/ZemezLab/jet-search/releases/tag/2.0.2) - 21.11.2019
* Added: FA5 compatibility
* Fixed: Various issue

## [2.0.1](https://github.com/ZemezLab/jet-search/releases/tag/2.0.1) - 16.10.2019
* Added: filter hook 'jet-search/ajax-search/meta_callbacks' to the Custom fields meta callbacks
* Added: `get_the_title` callback to the Custom fields meta callbacks

## [2.0.0](https://github.com/ZemezLab/jet-search/releases/tag/2.0.0) - 01.08.2019
* Added: include/exclude controls for terms and posts
* Added: the ability to display custom fields in the result area
* Added: the ability to search in custom fields
* Added: `Post Content Source` control
* Added: responsive control to the `Number of posts on one search page` control
* Added: dummy data
* Added: multiple performance improvements and bug fixes

## [1.1.4](https://github.com/ZemezLab/jet-search/releases/tag/1.1.4) - 04.06.2019
* Update: categories select arguments ( add 'orderby' => 'name' )
* Fixed: compatibility with Product Search Page created with Elementor Pro
* Fixed: minor css issue

## [1.1.3](https://github.com/ZemezLab/jet-search/releases/tag/1.1.3) - 26.04.2019
* Added: `Custom Width` and `Custom Position` controls for the Result Area Panel
* Added: `Vertical Align` control for the Submit Button
* Added: filter `jet-search/ajax-search/custom-post-data`

## [1.1.2](https://github.com/ZemezLab/jet-search/releases/tag/1.1.2) - 02.04.2019
* Added: `Placeholder Typography` control in the Ajax Search Widget
* Fixed: ajax error

## [1.1.1](https://github.com/ZemezLab/jet-search/releases/tag/1.1.1) - 27.03.2019
* Fixed: minor issues

## [1.1.0](https://github.com/ZemezLab/jet-search/releases/tag/1.1.0) - 20.03.2019
* Added: `Product Price` and `Product Rating` settings in the Ajax Search Widget
* Added: compatibility with Woo Search Result Page
* Added: better compatibility with Polylang
* Added: filter `jet-search/ajax-search/categories-select/args` for passed arguments to `wp_dropdown_categories`
* Added: Brazilian translations
* Added: multiple performance improvements and bug fixes

## [1.0.1](https://github.com/ZemezLab/jet-search/releases/tag/1.0.1)
* Fixed: minor issue bugs.

## [1.0.0](https://github.com/ZemezLab/jet-search/releases/tag/1.0.0)
* Init
