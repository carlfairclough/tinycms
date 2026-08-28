# TinyCMS

TinyCMS is a very small, database-free content management system. It is mostly a collection of folders wearing a CMS badge.

It was inspired by Ruby static-site generators: readable files, useful conventions, and the idea that the codebase should be readable. TinyCMS borrowed all of that, then took the slightly unusual final step of rendering the pages dynamically in PHP, so content can be added, changed, or removed without downtime or a build step.

There is no database, admin panel, package manager, build pipeline, or writable production state. Pages are folders. Content is text. The router looks at both and tries its hardest.

TinyCMS was broken out of the little publishing system I used to ship campaign sites for Fortune 500 companies. It is considerably more battle-tested than a CMS made of text files and spite has any right to be.

## Maintenance mode

TinyCMS is in maintenance mode. This is the least alarming way of saying that the experiment is finished and I am not going to spend the next decade slowly turning it into WordPress, which I originally built this to spite.

Security problems, current-PHP compatibility, and serious bugs may still be fixed. New features, a browser-based editor, and ambitious plans involving content APIs are unlikely. File formats will stay simple, stable, and without a saas model.

In hindsight, the section format in TinyCMS's text files is YAML written before I had been introduced to YAML. If I were going to add one thing, proper YAML support would probably be it.

## What it does

- turns folders under `pages/` into URLs
- reads content from small, named sections in text files
- removes numeric ordering prefixes from public URLs
- builds main navigation and submenus from those prefixes
- renders a deliberately small and safe subset of Markdown
- lets themes remain ordinary PHP and CSS
- loads local plugins when explicitly enabled
- returns an actual HTTP 404 when a page is missing, an innovation it took the original prototype some time to achieve

## What it does not do

- store anything in a database
- provide an admin area
- authenticate users
- edit files for you
- generate a static site, despite the inspiration
- have opinions about your deployment platform beyond “please do not expose `setup.txt` to the internet”

## Requirements

- PHP 8.0 or newer
- Apache 2.4 with `mod_rewrite` and `.htaccess` overrides enabled, or equivalent configuration in another web server
- this repository configured as the site's document root

TinyCMS does not need filesystem write access in production. It has nothing to write and nowhere in particular to write it.

## Try it

Run this from the repository root:

```sh
php -S 127.0.0.1:8080 index.php
```

Then open <http://127.0.0.1:8080>. The included pages form a small example site, including nested navigation, an unlisted page, and a project page using a different template.

The PHP development server is useful for looking at TinyCMS locally. It is not a production web server, even if leaving it running in a terminal feels impressively direct.

## How the files become a website

Site-wide settings live in `setup.txt`. Page content lives under `pages/`:

```text
pages/
├── home/page.txt                        → /
├── 01_about/page.txt                    → /about
├── 01_about/01_getting-started/page.txt → /about/getting-started
├── 02_work/01_sample-project/page.txt   → /work/sample-project
├── unlisted/page.txt                    → /unlisted
└── 404/page.txt                         → everything optimistic but incorrect
```

A folder beginning with two digits and an underscore appears in navigation. The prefix controls the order but disappears from the URL, so `01_about` becomes `/about` rather than `/01_about`, which would be unforgivable.

Numbered top-level folders appear in the main menu. Numbered immediate children appear in the submenu. Unnumbered folders still have public URLs but stay out of navigation.

An unlisted page is not a private page. Use proper server-side access controls for anything secret.

## Writing content

Settings and pages use named sections separated by four hyphens:

```text
----
Template:
Default
----
Title:
An example page
----
Content:
Plain text is escaped automatically.
----
Markdown content:
Optional **safe Markdown** goes here.
----
```

Section names are case-insensitive. Themes retrieve values with helpers such as `get_settings('Site Name')` and `get_content('Title')`.

`Content:` is plain text and is escaped by the bundled templates. `Markdown content:` understands headings, paragraphs, lists, blockquotes, horizontal rules, fenced code, inline code, emphasis, strong text, and links.

The Markdown renderer is intentionally small. Raw HTML is escaped and unsafe protocols such as `javascript:` are discarded. It is quite good at rendering the Markdown used by this example and has no desire to become a CommonMark standards body.

## Themes

`Theme:` in `setup.txt` selects a lowercase directory under `themes/`. `Template:` in a page selects `template-<name>.php` inside that theme. `Template: Portfolio`, for example, loads `template-portfolio.php`.

The default theme uses these helpers:

- `get_settings($name)`
- `get_content($name)`
- `print_markdown($name)`
- `site_url($path)`
- `theme_url_loc()`
- `subMenu()`
- `insert_body_classes()`

Custom themes should escape plain values with `e()`. Theme and template names are constrained to safe filename characters, and a missing file produces HTTP 500.

## Plugins

A plugin is a directory under `plugins/` containing `function.php` and a `config.txt` with:

```text
----
Status:
On
----
```

Enabled plugins are PHP code and run with the same privileges as TinyCMS. This makes the plugin system extremely flexible in the same way that handing someone your house keys makes their visit extremely flexible. I don't know if anybody has written or shared a plugin, but make sure you know what it does if you enable one.

The included gallery plugin is empty and disabled. Call it archaeology.

## Publishing it

The included `.htaccess` provides friendly routes and blocks public access to `.git`, `backend/`, `pages/`, `plugins/`, non-entry-point PHP files, and text source files.

Confirm those rules are active before publishing. If the site runs on Nginx, Caddy, or something else, reproduce them there. Without the deny rules, the content files and `setup.txt` are ordinary public files, and the web server will hand them to strangers with admirable efficiency.

TinyCMS also sends a restrictive Content Security Policy and common browser security headers. Missing pages return HTTP 404 and `X-Robots-Tag: noindex`.

Before deploying, check the PHP files:

```sh
find . -name '*.php' -print0 | xargs -0 -n1 php -l
```

Then visit `/`, `/about/getting-started`, `/work/sample-project`, and a URL that does not exist. Keep PHP and the web server updated. Maintenance mode means TinyCMS changes rarely; it does not mean old PHP installations become safer through neglect.

## Why this still exists

TinyCMS began as an experiment in bringing the nice file conventions of Ruby static-site tooling to an ordinary PHP host. It remains useful because the entire idea fits in one paragraph, the content survives without the software, and there are very few moving parts available to fall over.

It is small, odd, and finished. That is the feature.
