# Shalach Manot Ordering Website

## About

This is a custom-built website for a popular annual synagogue fundraiser that
involves sponsoring baskets for the Jewish holiday of Purim. We call it Shalach
Manot. Others may call it Misloach Manos or similar. The code and associated
artifacts represent a labor of love that has gone on for over a decade.

## Contents

* `admin.php` - order summary table, reports, and order submission tool for
  administrators

* `common.js` - misc. shared JavaScript utilities

* `common.php` - misc. shared PHP utilities

* `dynamicTable_c.js` - third-party library used by `admin.php`

* `ezajaz.js` - AJAX utility (created by me) for making asynchronous requests to
  the server and receiving the JSON replies; requests are queued so they are
  strictly sequenced and can be retried

* `index.php` - main page for basket ordering; basically a single page web app
  all in one file, including PHP, CSS, HTML, and JS (yes, it's ugly, but it's
  also convenient and it works)

* `json2.js` - third-party library for parsing JSON structures (may not be
  required any more with newer versions of JS)

* `payment.php` - online payment page; now just a wrapper for online
  donation/payment through ShulCloud

* `schema.sql` - DDL for creating tables, views, and indexes in MySQL

* `Shalach_Manot.htm` - info/help page about the program, exported from word
  doc `docs/Shalach_Manot.doc`

* `shul-settings.php` - globals that control some of the site's behavior,
  including the DB connection and email addresses; note that not all
  synagogue-specific configuration exists here (but maybe someday it will)

### Directories

* `docs` - source Word doc for about page and some docs for maintainers of the
  site

* `images` - static images for admin and ordering sites

* `mail` - folder in which outgoing emails are written when running the site
  locally in test mode

* `Shalach_Manot_files` - files supporting `Shalach_Manot.htm`

* `styles` - CSS used by DynamicTable; (rest of CSS is embedded with HTML)

## Contributing

Although I don't anticipate any, contributions are certainly welcome. Please
contact me first.

1. Fork the project
2. Create your feature branch
3. Add your modifications
4. Push to the branch
5. Create new Pull Request

## License

shalach-manot is licenses with [GNU GPL v3](./LICENSE).
