<?php

declare(strict_types=1);

/** Escape text for an HTML text or attribute context. */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Read TinyCMS's simple `Name:` / `----` section format.
 *
 * @return array<string, string>
 */
function parse_sections(string $filename): array
{
    $contents = @file_get_contents($filename);
    if ($contents === false) {
        return [];
    }

    $sections = [];
    $key = null;
    $buffer = [];

    $flush = static function () use (&$sections, &$key, &$buffer): void {
        if ($key !== null) {
            $sections[strtolower($key)] = trim(implode("\n", $buffer));
        }
        $key = null;
        $buffer = [];
    };

    foreach (preg_split('/\R/u', $contents) ?: [] as $line) {
        if (preg_match('/^\s*-{4}/', $line) === 1) {
            $flush();
            continue;
        }

        if ($key === null && preg_match('/^\s*([^:\r\n]+):\s*(.*)$/u', $line, $matches) === 1) {
            $key = trim($matches[1]);
            if ($matches[2] !== '') {
                $buffer[] = $matches[2];
            }
            continue;
        }

        if ($key !== null) {
            $buffer[] = $line;
        }
    }

    $flush();

    return $sections;
}

/** Convert a directory name such as `01_about` to its URL slug. */
function directory_slug(string $directory): string
{
    return preg_replace('/^\d{2}_/', '', basename($directory)) ?? basename($directory);
}

/** Build a local URL without trusting the request's Host header. */
function site_url(string $path = ''): string
{
    $path = ltrim($path, '/');
    $prefix = BASE_PATH === '' ? '' : BASE_PATH;

    return $prefix.'/'.($path === '' ? '' : $path);
}

/** @return list<string> */
function content_directories(string $parent): array
{
    $directories = glob($parent.'/*', GLOB_ONLYDIR) ?: [];
    sort($directories, SORT_NATURAL | SORT_FLAG_CASE);

    return array_values($directories);
}

/** Resolve URL segments one directory at a time, without path traversal. */
function resolve_page_directory(string $request): ?string
{
    if ($request === '' || str_contains($request, "\0")) {
        return null;
    }

    $current = ROOT.'/pages';
    foreach (explode('/', $request) as $segment) {
        if ($segment === '' || $segment === '.' || $segment === '..') {
            return null;
        }

        $match = null;
        foreach (content_directories($current) as $directory) {
            if (strcasecmp(directory_slug($directory), $segment) === 0) {
                $match = $directory;
                break;
            }
        }

        if ($match === null) {
            return null;
        }
        $current = $match;
    }

    return is_file($current.'/page.txt') ? $current : null;
}

/** @var array<string, string> $settings */
$settings = parse_sections(ROOT.'/setup.txt');

function get_settings(string $term): string
{
    global $settings;

    return $settings[strtolower($term)] ?? '';
}

$isHome = REQUEST === '';
$currentDirectory = $isHome ? ROOT.'/pages/home' : resolve_page_directory(REQUEST);
$pageNotFound = $currentDirectory === null || !is_file($currentDirectory.'/page.txt');

if ($pageNotFound) {
    http_response_code(404);
    header('X-Robots-Tag: noindex');
    $currentDirectory = ROOT.'/pages/404';
}

/** @var array<string, string> $pageContent */
$pageContent = parse_sections($currentDirectory.'/page.txt');

function get_content(string $term): string
{
    global $pageContent;

    return $pageContent[strtolower($term)] ?? '';
}

function is_home(): bool
{
    global $isHome, $pageNotFound;

    return $isHome && !$pageNotFound;
}

/** @return string Safe HTML for the main navigation. */
function main_navigation(): string
{
    $items = [];
    foreach (content_directories(ROOT.'/pages') as $directory) {
        $folder = basename($directory);
        if (preg_match('/^\d{2}_/', $folder) !== 1 || !is_file($directory.'/page.txt')) {
            continue;
        }

        $slug = directory_slug($directory);
        $label = str_replace(['-', '_'], ' ', $slug);
        $items[] = sprintf(
            '<li class="main-nav-item main-nav-item-%s"><a href="%s">%s</a></li>',
            e(preg_replace('/[^a-z0-9_-]/i', '-', $slug) ?? ''),
            e(site_url($slug)),
            e($label)
        );
    }

    return '<ul class="main-nav">'.implode('', $items).'</ul>';
}

$get_main_nav = main_navigation();

/** Print navigation for numbered child pages of the current page. */
function subMenu(): void
{
    global $currentDirectory, $pageNotFound;

    if ($pageNotFound) {
        return;
    }

    $items = [];
    foreach (content_directories($currentDirectory) as $directory) {
        $folder = basename($directory);
        if (preg_match('/^\d{2}_/', $folder) !== 1 || !is_file($directory.'/page.txt')) {
            continue;
        }

        $slug = directory_slug($directory);
        $label = str_replace(['-', '_'], ' ', $slug);
        $parent = REQUEST === '' ? '' : REQUEST.'/';
        $items[] = sprintf(
            '<li class="sub-nav-item sub-nav-item-%s"><a href="%s">%s</a></li>',
            e(preg_replace('/[^a-z0-9_-]/i', '-', $slug) ?? ''),
            e(site_url($parent.$slug)),
            e($label)
        );
    }

    if ($items !== []) {
        echo '<ul class="sub-nav">'.implode('', $items).'</ul>';
    }
}

function insert_body_classes(): void
{
    global $pageNotFound;

    $classes = [];
    if ($pageNotFound) {
        $classes[] = 'error-404';
    } elseif (is_home()) {
        $classes[] = 'home';
    } else {
        foreach (explode('/', REQUEST) as $segment) {
            $classes[] = 'page-'.(preg_replace('/[^a-z0-9_-]/i', '-', $segment) ?? '');
        }
    }

    $template = strtolower(get_content('Template'));
    $classes[] = 'template-'.(preg_replace('/[^a-z0-9_-]/i', '-', $template) ?? 'default');
    echo e(implode(' ', array_filter($classes)));
}

/** Return a safe Markdown link destination, or null for scripting protocols. */
function safe_markdown_url(string $url): ?string
{
    $url = trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    $url = preg_replace('/[\x00-\x20\x7f]+/u', '', $url) ?? '';
    if ($url === '') {
        return null;
    }

    if (preg_match('/^([a-z][a-z0-9+.-]*):/i', $url, $matches) === 1) {
        return in_array(strtolower($matches[1]), ['http', 'https', 'mailto'], true) ? $url : null;
    }

    return $url;
}

/** Render the inline Markdown used by page files, with HTML escaped first. */
function markdown_inline(string $text): string
{
    $text = str_replace("\x1a", '', $text);
    $tokens = [];
    $token = static function (string $html) use (&$tokens): string {
        $key = "\x1a".count($tokens)."\x1a";
        $tokens[$key] = $html;

        return $key;
    };

    $text = preg_replace_callback('/`([^`\n]+)`/', static function (array $matches) use ($token): string {
        return $token('<code>'.e($matches[1]).'</code>');
    }, $text) ?? $text;

    $text = preg_replace_callback(
        '/\[([^\]\n]+)\]\(([^\s\)]+)(?:\s+["\']([^"\']*)["\'])?\)/',
        static function (array $matches) use ($token): string {
            $url = safe_markdown_url($matches[2]);
            if ($url === null) {
                return $token(e($matches[1]));
            }

            $title = isset($matches[3]) && $matches[3] !== '' ? ' title="'.e($matches[3]).'"' : '';
            return $token('<a href="'.e($url).'"'.$title.'>'.e($matches[1]).'</a>');
        },
        $text
    ) ?? $text;

    $text = e($text);
    $text = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $text) ?? $text;
    $text = preg_replace('/__(.+?)__/s', '<strong>$1</strong>', $text) ?? $text;
    $text = preg_replace('/(?<!\*)\*([^*\n]+)\*(?!\*)/', '<em>$1</em>', $text) ?? $text;
    $text = preg_replace('/(?<!_)_([^_\n]+)_(?!_)/', '<em>$1</em>', $text) ?? $text;

    return strtr($text, $tokens);
}

/**
 * Render a deliberately small Markdown subset without executable third-party
 * code. Raw HTML is always displayed as text.
 */
function markdown_html(string $markdown): string
{
    $lines = preg_split('/\R/u', trim($markdown)) ?: [];
    $output = [];
    $paragraph = [];
    $listType = null;
    $codeLines = null;

    $closeParagraph = static function () use (&$output, &$paragraph): void {
        if ($paragraph !== []) {
            $output[] = '<p>'.markdown_inline(implode(' ', $paragraph)).'</p>';
            $paragraph = [];
        }
    };
    $closeList = static function () use (&$output, &$listType): void {
        if ($listType !== null) {
            $output[] = '</'.$listType.'>';
            $listType = null;
        }
    };

    foreach ($lines as $line) {
        if ($codeLines !== null) {
            if (preg_match('/^\s*```/', $line) === 1) {
                $output[] = '<pre><code>'.e(implode("\n", $codeLines)).'</code></pre>';
                $codeLines = null;
            } else {
                $codeLines[] = $line;
            }
            continue;
        }

        if (preg_match('/^\s*```/', $line) === 1) {
            $closeParagraph();
            $closeList();
            $codeLines = [];
            continue;
        }

        if (trim($line) === '') {
            $closeParagraph();
            $closeList();
            continue;
        }

        if (preg_match('/^(#{1,6})\s*(.+?)\s*#*$/', $line, $matches) === 1) {
            $closeParagraph();
            $closeList();
            $level = strlen($matches[1]);
            $output[] = '<h'.$level.'>'.markdown_inline($matches[2]).'</h'.$level.'>';
            continue;
        }

        if (preg_match('/^\s*([-+*])\s+(.+)$/', $line, $matches) === 1) {
            $closeParagraph();
            if ($listType !== 'ul') {
                $closeList();
                $output[] = '<ul>';
                $listType = 'ul';
            }
            $output[] = '<li>'.markdown_inline($matches[2]).'</li>';
            continue;
        }

        if (preg_match('/^\s*(\d+)[.)]\s+(.+)$/', $line, $matches) === 1) {
            $closeParagraph();
            if ($listType !== 'ol') {
                $closeList();
                $start = (int) $matches[1];
                $output[] = '<ol'.($start === 1 ? '' : ' start="'.$start.'"').'>';
                $listType = 'ol';
            }
            $output[] = '<li>'.markdown_inline($matches[2]).'</li>';
            continue;
        }

        if (preg_match('/^\s*>\s?(.*)$/', $line, $matches) === 1) {
            $closeParagraph();
            $closeList();
            $output[] = '<blockquote><p>'.markdown_inline($matches[1]).'</p></blockquote>';
            continue;
        }

        if (preg_match('/^\s*((\*\s*){3,}|(-\s*){3,}|(_\s*){3,})$/', $line) === 1) {
            $closeParagraph();
            $closeList();
            $output[] = '<hr>';
            continue;
        }

        $closeList();
        $paragraph[] = trim($line);
    }

    if ($codeLines !== null) {
        $output[] = '<pre><code>'.e(implode("\n", $codeLines)).'</code></pre>';
    }
    $closeParagraph();
    $closeList();

    return implode("\n", $output);
}

function print_markdown(string $term): void
{
    echo markdown_html(get_content($term));
}

// Enabled plugins are trusted local PHP code, like the application itself.
foreach (content_directories(ROOT.'/plugins') as $plugin) {
    $config = parse_sections($plugin.'/config.txt');
    $function = $plugin.'/function.php';
    if (strcasecmp($config['status'] ?? '', 'On') === 0 && is_file($function)) {
        require_once $function;
    }
}

$themeName = strtolower(get_settings('Theme'));
$themeName = preg_replace('/[^a-z0-9_-]/', '', $themeName) ?: 'default';
$themeDirectory = ROOT.'/themes/'.$themeName;
if (!is_dir($themeDirectory)) {
    http_response_code(500);
    exit('Configured theme was not found.');
}

$templateName = strtolower(get_content('Template'));
$templateName = preg_replace('/[^a-z0-9_-]/', '', $templateName) ?: 'default';
$templateFile = $themeDirectory.'/template-'.$templateName.'.php';
if (!is_file($templateFile)) {
    http_response_code(500);
    exit('Configured page template was not found.');
}

function theme_server_loc(): string
{
    global $themeDirectory;

    return $themeDirectory;
}

function theme_url_loc(): string
{
    global $themeName;

    return site_url('themes/'.$themeName);
}

require $themeDirectory.'/header.php';
require $templateFile;
require $themeDirectory.'/footer.php';
