import fs from 'node:fs/promises';
import path from 'node:path';
import { execSync } from 'node:child_process';

let _gitSha = null;
function getGitSha() {
    if (_gitSha === null) {
        try {
            _gitSha = `<!-- ${execSync('git rev-parse --short HEAD', { encoding: 'utf-8' }).trim()} -->`;
        } catch {
            _gitSha = '';
        }
    }
    return _gitSha;
}

function htmlEscape(s) {
    return String(s)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#39;');
}

function findTagEnd(src, startIdx) {
    // Finds the closing '>' for a tag starting at startIdx, respecting quoted attribute values.
    let i = startIdx;
    let quote = null;
    for (; i < src.length; i++) {
        const ch = src[i];
        if (quote) {
            if (ch === quote) quote = null;
            continue;
        }
        if (ch === '"' || ch === "'") {
            quote = ch;
            continue;
        }
        if (ch === '>') return i;
    }
    return -1;
}

function parseAttributes(attrSrc) {
    // Minimal HTML attribute parser:
    // - foo="bar" or foo='bar' -> string
    // - bare boolean attrs like `disabled` -> true
    const attrs = {};
    let i = 0;
    const s = attrSrc.trim();

    while (i < s.length) {
        while (i < s.length && /\s/.test(s[i])) i++;
        if (i >= s.length) break;

        // name
        let nameStart = i;
        while (i < s.length && /[^\s=]/.test(s[i])) i++;
        const name = s.slice(nameStart, i);
        if (!name) break;

        while (i < s.length && /\s/.test(s[i])) i++;

        if (s[i] !== '=') {
            attrs[name] = true;
            continue;
        }

        i++; // =
        while (i < s.length && /\s/.test(s[i])) i++;

        const q = s[i];
        if (q === '"' || q === "'") {
            i++;
            const valStart = i;
            while (i < s.length && s[i] !== q) i++;
            attrs[name] = s.slice(valStart, i);
            if (s[i] === q) i++;
        } else {
            // unquoted value
            const valStart = i;
            while (i < s.length && !/\s/.test(s[i])) i++;
            attrs[name] = s.slice(valStart, i);
        }
    }

    return attrs;
}

function renderIfBlocks(src, props) {
    // Stack-based parser; supports nesting + optional else:
    // [[#if flag]] ... [[else]] ... [[/if]]
    const tokenRe = /\[\[#if\s+([a-zA-Z0-9_-]+)\s*\]\]|\[\[else\]\]|\[\[\/if\]\]/g;

    /** @type {Array<{flag:string, thenParts:string[], elseParts:string[], inElse:boolean}>} */
    const stack = [];
    let out = '';
    let last = 0;
    let m;

    function appendText(t) {
        if (!t) return;
        if (stack.length === 0) {
            out += t;
            return;
        }
        const top = stack[stack.length - 1];
        (top.inElse ? top.elseParts : top.thenParts).push(t);
    }

    while ((m = tokenRe.exec(src))) {
        const idx = m.index;
        const tok = m[0];

        appendText(src.slice(last, idx));

        if (tok.startsWith('[[#if')) {
            const flag = m[1];
            stack.push({ flag, thenParts: [], elseParts: [], inElse: false });
            last = idx + tok.length;
            continue;
        }

        if (tok === '[[else]]') {
            if (stack.length === 0) {
                appendText(tok);
            } else {
                stack[stack.length - 1].inElse = true;
            }
            last = idx + tok.length;
            continue;
        }

        if (tok === '[[/if]]') {
            if (stack.length === 0) {
                appendText(tok);
            } else {
                const frame = stack.pop();
                const enabled = !!props[frame.flag];
                const chosen = enabled ? frame.thenParts.join('') : frame.elseParts.join('');
                appendText(chosen);
            }
            last = idx + tok.length;
            continue;
        }
    }

    appendText(src.slice(last));

    while (stack.length) {
        const frame = stack.shift();
        const literal =
            `[[#if ${frame.flag}]]` +
            frame.thenParts.join('') +
            (frame.inElse ? '[[else]]' + frame.elseParts.join('') : '');
        out = literal + out;
    }

    return out;
}

function mergeRootClass(renderedHtml, extraClass) {
    if (!extraClass) return renderedHtml;
    const extra = String(extraClass).trim();
    if (!extra) return renderedHtml;

    if (renderedHtml.includes('[[class]]') || renderedHtml.includes('[[{class}]]')) {
        return renderedHtml;
    }

    const lt = renderedHtml.indexOf('<');
    if (lt === -1) return renderedHtml;

    const isComment = renderedHtml.startsWith('<!--', lt);
    const isDoc = renderedHtml.toLowerCase().startsWith('<!doctype', lt);
    if (isComment || isDoc) return renderedHtml;

    const gt = findTagEnd(renderedHtml, lt);
    if (gt === -1) return renderedHtml;

    const openTag = renderedHtml.slice(lt, gt + 1);
    if (openTag.startsWith('</')) return renderedHtml;

    const tagMatch = openTag.match(/^<\s*([a-zA-Z][a-zA-Z0-9:-]*)\b/);
    if (!tagMatch) return renderedHtml;
    const tagName = tagMatch[1];
    const selfClosing = openTag.endsWith('/>');

    const attrSegment = openTag.slice(tagMatch[0].length).replace(/\/?>$/, '');

    const attrs = parseAttributes(attrSegment);

    const existing = typeof attrs.class === 'string' ? attrs.class.trim() : '';
    const merged = existing ? `${existing} ${extra}` : extra;

    attrs.class = merged;
    const rebuiltAttrs = Object.entries(attrs)
        .filter(([k]) => !isBuildAttr(k))
        .map(([k, v]) => {
            if (v === true) return k;
            return `${k}="${String(v).replaceAll('"', '&quot;')}"`;
        })
        .join(' ');

    const rebuilt = `<${tagName}${rebuiltAttrs ? ' ' + rebuiltAttrs : ''}${selfClosing ? ' />' : '>'}`;
    return renderedHtml.slice(0, lt) + rebuilt + renderedHtml.slice(gt + 1);
}

function renderComponentTemplate(tplSrc, props, slotHtml) {
    let out = tplSrc;

    out = renderIfBlocks(out, props);

    out = out.replaceAll('[[slot]]', slotHtml ?? '');

    // [[prop]] escapes by default; [[{prop}]] is raw
    out = out.replace(/\[\[\{([a-zA-Z0-9_-]+)\}\]\]/g, (_, k) => String(props[k] ?? ''));
    out = out.replace(/\[\[([a-zA-Z0-9_-]+)\]\]/g, (_, k) => htmlEscape(props[k] ?? ''));

    if (typeof props.class === 'string') {
        out = mergeRootClass(out, props.class);
    }

    return out;
}

function findMatchingCloseForTag(src, tagName, fromIdx) {
    // An opening tag is the name followed by whitespace, `/` or `>`. A plain
    // indexOf('<' + name) also matched longer names that start with it, so a
    // <project-facts> inside a <project> counted as a second <project> and the
    // outer tag never closed.
    const openRe = new RegExp(`<${escapeRegex(tagName)}(?=[\\s/>])`, 'g');
    // HTML5 allows whitespace between the tag name and `>` in end tags
    // (`</lnk>` and `</lnk\n>` are both valid). Prettier emits the latter
    // form when wrapping long attribute lists on inline elements, so we
    // need to tolerate it or those pages fail to build.
    const closeRe = new RegExp(`<\\/${escapeRegex(tagName)}\\s*>`, 'g');
    let depth = 1;
    let i = fromIdx;

    while (i < src.length) {
        openRe.lastIndex = i;
        const openMatch = openRe.exec(src);
        const nextOpen = openMatch ? openMatch.index : -1;
        closeRe.lastIndex = i;
        const closeMatch = closeRe.exec(src);
        const nextClose = closeMatch ? closeMatch.index : -1;

        if (nextClose === -1) return null;
        if (nextOpen !== -1 && nextOpen < nextClose) {
            depth++;
            i = nextOpen + openMatch[0].length;
            continue;
        }

        depth--;
        if (depth === 0) {
            return { start: nextClose, end: nextClose + closeMatch[0].length };
        }
        i = nextClose + closeMatch[0].length;
    }

    return null;
}

function escapeRegex(s) {
    return s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

function isCustomComponentTagName(tagName) {
    return /^[a-z][a-z0-9-]*-[a-z0-9-]+$/i.test(tagName);
}

function pascalFromKebab(s) {
    return s
        .split('-')
        .filter(Boolean)
        .map((p) => p[0].toUpperCase() + p.slice(1))
        .join('');
}

function toComponentsDirArray(componentsDir) {
    if (Array.isArray(componentsDir)) return componentsDir;
    return [componentsDir];
}

async function fileExists(p) {
    try {
        await fs.access(p);
        return true;
    } catch {
        return false;
    }
}

// Attributes handlr-build reads for itself carry an hb- prefix, so every other
// name (file, name, src, ...) is free to be an ordinary component prop. They
// never reach the rendered root element.
const isBuildAttr = (name) => name.startsWith('hb-');

async function resolveComponentTemplatePath({ componentsDirs, componentNames, attrs, tagName }) {
    const file = typeof attrs['hb-file'] === 'string' ? attrs['hb-file'] : null;
    const name = typeof attrs['hb-name'] === 'string' ? attrs['hb-name'] : null;

    // Explicit hb-file= attribute: try each dir in order, first match wins
    if (file) {
        for (const dir of componentsDirs) {
            const candidate = path.resolve(dir, file);
            if (await fileExists(candidate)) return candidate;
        }
        // Fall back to last candidate so the error message is meaningful
        return path.resolve(componentsDirs[componentsDirs.length - 1], file);
    }

    // <component hb-name="foo" />: try each dir in order
    if (tagName === 'component') {
        if (!name) {
            const renamed = attrs.name !== undefined || attrs.file !== undefined;
            throw new Error(
                'Component tag missing `hb-name` or `hb-file`: <component ...>' +
                    (renamed ? ' (`name=` and `file=` were renamed `hb-name=` and `hb-file=` in 0.18)' : '')
            );
        }
        const filename = `${name}.html`;
        for (const dir of componentsDirs) {
            const candidate = path.resolve(dir, filename);
            if (await fileExists(candidate)) return candidate;
        }
        return path.resolve(componentsDirs[componentsDirs.length - 1], filename);
    }

    const lower = String(tagName).toLowerCase();
    const isKnown = componentNames?.has(lower);

    if (!isCustomComponentTagName(tagName) && !isKnown) {
        throw new Error(
            `Unknown component tag: <${tagName}> (expected <component name="...">, a custom element tag like <my-component>, or a tag with a matching template in a components directory)`
        );
    }

    // Try kebab name in each dir, then PascalCase name in each dir
    const kebabFile = `${tagName}.html`;
    const pascalFile = `${pascalFromKebab(tagName)}.html`;

    for (const dir of componentsDirs) {
        const candidate = path.resolve(dir, kebabFile);
        if (await fileExists(candidate)) return candidate;
    }
    for (const dir of componentsDirs) {
        const candidate = path.resolve(dir, pascalFile);
        if (await fileExists(candidate)) return candidate;
    }

    // Nothing found — return last kebab candidate so the error from fs.readFile is meaningful
    return path.resolve(componentsDirs[componentsDirs.length - 1], kebabFile);
}

/**
 * <code-file src="dns/dns.php" /> is replaced by that file's contents, HTML-
 * escaped, read from siteConfig.codeRoot. It's for showing real, tested code
 * on a page from one copy of it. Escaped text only, no wrapper: put it inside
 * whatever pre or code markup the site uses. Optional lines="3-8" shows just
 * those lines (1-based, inclusive). The trailing newline is dropped, and `[`
 * is written as &#91; so code containing [[x]] can't be read as a placeholder.
 * A missing codeRoot or file is a build error.
 */
// ── Build-time syntax highlighting (optional; needs the shiki package) ──
//
// Colors come out as CSS variables (Shiki's css-variables theme), never fixed
// colors, so each site maps them to its own palette, light and dark:
// --shiki-foreground, --shiki-token-keyword, --shiki-token-string,
// --shiki-token-comment, --shiki-token-constant, --shiki-token-function, and
// the rest of Shiki's set. The output is spans only, no pre or code, so it
// goes inside whatever markup the site already uses.

let highlighterPromise = null;

async function getHighlighter() {
    if (!highlighterPromise) {
        highlighterPromise = (async () => {
            let shiki;
            try {
                shiki = await import('shiki');
            } catch {
                throw new Error('siteConfig.highlight is on, but the shiki package is missing: npm install -D shiki');
            }
            const theme = shiki.createCssVariablesTheme({
                name: 'css-variables',
                variablePrefix: '--shiki-',
                fontStyle: true,
            });
            return shiki.createHighlighter({ themes: [theme], langs: [] });
        })();
    }
    return highlighterPromise;
}

// Escaped for the page: HTML-escaped, and `[` as &#91; so code containing
// [[something]] is never read as a placeholder.
function escapeCode(text) {
    return htmlEscape(text).replaceAll('[', '&#91;');
}

function decodeEntities(text) {
    return text
        .replace(/&#91;/g, '[')
        .replace(/&lt;/g, '<')
        .replace(/&gt;/g, '>')
        .replace(/&quot;/g, '"')
        .replace(/&#39;/g, "'")
        .replace(/&amp;/g, '&');
}

async function highlightCode(code, lang) {
    const highlighter = await getHighlighter();
    if (!highlighter.getLoadedLanguages().includes(lang)) {
        try {
            await highlighter.loadLanguage(lang);
        } catch {
            throw new Error(`Unknown highlight language "${lang}" (any language Shiki knows: php, html, sql, shellscript, ...)`);
        }
    }
    const { tokens } = highlighter.codeToTokens(code, { lang, theme: 'css-variables' });
    return tokens
        .map((line) =>
            line
                .map((token) => {
                    const text = escapeCode(token.content);
                    const styles = [];
                    if (token.color && token.color !== 'var(--shiki-foreground)') styles.push(`color:${token.color}`);
                    if (token.fontStyle & 1) styles.push('font-style:italic');
                    if (token.fontStyle & 2) styles.push('font-weight:bold');
                    if (token.fontStyle & 4) styles.push('text-decoration:underline');
                    return styles.length ? `<span style="${styles.join(';')}">${text}</span>` : text;
                })
                .join('')
        )
        .join('\n');
}

// The language for a file with no lang= given, from its name. Unknown names get
// no highlighting rather than a guess.
const LANG_BY_EXTENSION = {
    php: 'php', sql: 'sql', js: 'javascript', mjs: 'javascript', ts: 'typescript', json: 'json',
    html: 'html', htm: 'html', css: 'css', yml: 'yaml', yaml: 'yaml', sh: 'shellscript',
    bash: 'shellscript', ini: 'ini', env: 'dotenv', md: 'markdown', xml: 'xml', csv: 'csv',
    toml: 'toml',
};
export function langForFile(file) {
    const base = path.basename(file).toLowerCase();
    if (base === 'dockerfile' || base.startsWith('dockerfile.')) return 'docker';
    if (base === '.env' || base.startsWith('.env.')) return 'dotenv';
    if (base === 'nginx.conf' || (base.endsWith('.conf') && base.includes('nginx'))) return 'nginx';
    const ext = base.includes('.') ? base.split('.').pop() : '';
    return LANG_BY_EXTENSION[ext] ?? null;
}

async function renderCodeFile(attrs, codeRoot, highlight = false) {
    const src = typeof attrs.src === 'string' ? attrs.src : '';
    if (!src) throw new Error('<code-file> needs src="path/to/file", relative to siteConfig.codeRoot');
    if (!codeRoot) throw new Error(`<code-file src="${src}">: set codeRoot in site.config.js to the folder it reads from`);

    const file = path.resolve(codeRoot, src);
    if (path.relative(codeRoot, file).startsWith('..')) {
        throw new Error(`<code-file src="${src}"> points outside codeRoot (${codeRoot})`);
    }
    let text;
    try {
        text = await fs.readFile(file, 'utf-8');
    } catch {
        throw new Error(`<code-file src="${src}">: no such file in codeRoot (${file})`);
    }
    text = text.replace(/\r?\n$/, '');

    if (typeof attrs.lines === 'string') {
        const m = attrs.lines.match(/^(\d+)(?:-(\d+))?$/);
        if (!m) throw new Error(`<code-file src="${src}" lines="${attrs.lines}">: lines must look like "3" or "3-8"`);
        const from = Number(m[1]);
        const to = m[2] ? Number(m[2]) : from;
        text = text.split(/\r?\n/).slice(from - 1, to).join('\n');
    }

    if (highlight) {
        // lang="" or no lang: guessed from the file name; lang="text" turns it off.
        const lang = typeof attrs.lang === 'string' && attrs.lang ? attrs.lang : langForFile(src);
        if (lang && lang !== 'text') return highlightCode(text, lang);
    }
    return escapeCode(text);
}

async function expandComponents(html, { componentsDirs, componentNames, codeRoot = null, highlight = false, maxPasses = 50 }) {
    let src = html;

    for (let pass = 0; pass < maxPasses; pass++) {
        let changed = false;
        let idx = 0;

        while (idx < src.length) {
            const lt = src.indexOf('<', idx);
            if (lt === -1) break;

            if (
                src.startsWith('</', lt) ||
                src.startsWith('<!--', lt) ||
                src.toLowerCase().startsWith('<!doctype', lt)
            ) {
                idx = lt + 2;
                continue;
            }

            const tagEnd = findTagEnd(src, lt);
            if (tagEnd === -1) break;

            const openTag = src.slice(lt, tagEnd + 1);
            const tagMatch = openTag.match(/^<\s*([a-zA-Z][a-zA-Z0-9:-]*)\b/);
            if (!tagMatch) {
                idx = tagEnd + 1;
                continue;
            }

            const tagName = tagMatch[1];
            const tagLower = tagName.toLowerCase();
            const isComponentTag =
                tagLower === 'component' ||
                isCustomComponentTagName(tagName) ||
                (componentNames?.has(tagLower) ?? false);
            if (!isComponentTag) {
                idx = tagEnd + 1;
                continue;
            }

            const selfClosing = openTag.endsWith('/>');
            const attrText = openTag.slice(tagMatch[0].length).replace(/\/?>$/, '');
            const attrs = parseAttributes(attrText);

            if (tagLower === 'code-file') {
                let end = tagEnd + 1;
                if (!selfClosing) {
                    const close = findMatchingCloseForTag(src, tagName, tagEnd + 1);
                    if (!close) throw new Error(`Unclosed <${tagName}> tag near: ${openTag}`);
                    end = close.end;
                }
                const code = await renderCodeFile(attrs, codeRoot, highlight);
                src = src.slice(0, lt) + code + src.slice(end);
                changed = true;
                idx = lt + code.length;
                continue;
            }

            // <code-highlight lang="php">already-escaped code</code-highlight>:
            // highlights inline code, for a component that wraps its slot in it.
            // With highlighting off, or no lang, the code passes through as is.
            if (tagLower === 'code-highlight') {
                let inner = '';
                let end = tagEnd + 1;
                if (!selfClosing) {
                    const close = findMatchingCloseForTag(src, tagName, tagEnd + 1);
                    if (!close) throw new Error(`Unclosed <${tagName}> tag near: ${openTag}`);
                    inner = src.slice(tagEnd + 1, close.start);
                    end = close.end;
                }
                const lang = typeof attrs.lang === 'string' ? attrs.lang : '';
                const out = highlight && lang ? await highlightCode(decodeEntities(inner), lang) : inner;
                src = src.slice(0, lt) + out + src.slice(end);
                changed = true;
                idx = lt + out.length;
                continue;
            }

            const componentPath = await resolveComponentTemplatePath({
                componentsDirs,
                componentNames,
                attrs,
                tagName: tagName.toLowerCase(),
            });
            // Trim surrounding whitespace from the template so the trailing
            // newline that every editor adds doesn't bleed into the page after
            // an inline component (e.g. `<lnk>here</lnk>,` should render as
            // `here,`, not `here ,`).
            const tplSrc = (await fs.readFile(componentPath, 'utf-8')).trim();

            let slotHtml = '';
            let closeEnd = tagEnd + 1;

            if (!selfClosing) {
                const closeMatch = findMatchingCloseForTag(src, tagName, tagEnd + 1);
                if (!closeMatch) {
                    throw new Error(`Unclosed <${tagName}> tag near: ${openTag}`);
                }
                slotHtml = src.slice(tagEnd + 1, closeMatch.start);
                closeEnd = closeMatch.end;
            }

            const props = { ...attrs };
            for (const [k, v] of Object.entries(props)) {
                if (v === true) props[k] = true;
            }

            const rendered = renderComponentTemplate(tplSrc, props, slotHtml);

            src = src.slice(0, lt) + rendered + src.slice(closeEnd);
            changed = true;
            idx = lt + rendered.length;
        }

        if (!changed) return src;
    }

    throw new Error(`Component expansion exceeded max passes (${maxPasses}). Possible recursive component loop?`);
}

async function expandTemplateSrc(html, pageDir) {
    let src = html;
    let idx = 0;

    while (idx < src.length) {
        const lt = src.indexOf('<template', idx);
        if (lt === -1) break;

        const ch = src[lt + '<template'.length];
        if (ch && ch !== '>' && !/\s/.test(ch)) {
            idx = lt + 1;
            continue;
        }

        const tagEnd = findTagEnd(src, lt);
        if (tagEnd === -1) break;

        const openTag = src.slice(lt, tagEnd + 1);
        const attrText = openTag.slice('<template'.length).replace(/\/?>$/, '');
        const attrs = parseAttributes(attrText);

        if (!attrs.src || typeof attrs.src !== 'string') {
            idx = tagEnd + 1;
            continue;
        }

        const closeMatch = findMatchingCloseForTag(src, 'template', tagEnd + 1);
        if (!closeMatch) {
            throw new Error(`Unclosed <template src="${attrs.src}"> near: ${openTag}`);
        }
        const closeEnd = closeMatch.end;

        const filePath = path.resolve(pageDir, `${attrs.src}.html`);
        const tplSrc = await fs.readFile(filePath, 'utf-8');

        const props = { ...attrs };
        delete props.src;
        const rendered = renderComponentTemplate(tplSrc, props, '');

        const outputAttrs = Object.entries(attrs)
            .filter(([k]) => k !== 'src')
            .map(([k, v]) => {
                if (v === true) return k;
                return `${k}="${String(v).replaceAll('"', '&quot;')}"`;
            })
            .join(' ');

        const output = `<template${outputAttrs ? ' ' + outputAttrs : ''}>${rendered}</template>`;
        src = src.slice(0, lt) + output + src.slice(closeEnd);
        idx = lt + output.length;
    }

    return src;
}

/** Absolute codeRoot from siteConfig (relative to root), or null when unset. */
export function resolveCodeRoot(siteConfig = {}, root = process.cwd()) {
    const codeRoot = siteConfig?.codeRoot;
    if (!codeRoot) return null;
    return path.resolve(root || process.cwd(), String(codeRoot));
}

function extractLayoutDeclaration(pageSrc) {
    const layoutTagRe = /^\s*<layout\s+([^>]*?)\/>\s*/i;
    const match = pageSrc.match(layoutTagRe);

    if (match) {
        const attrString = match[1];
        const attrs = parseAttributes(attrString);

        return {
            layoutName: attrs.name || 'base',
            title: attrs.title || null,
            pageContent: pageSrc.slice(match[0].length),
        };
    }

    return {
        layoutName: 'base',
        title: null,
        pageContent: pageSrc,
    };
}

function extractPageHead(pageSrc) {
    const pageHeadRe = /^\s*<page-head>([\s\S]*?)<\/page-head>\s*/i;
    const match = pageSrc.match(pageHeadRe);

    if (match) {
        return {
            pageHead: match[1].trim(),
            pageContent: pageSrc.slice(match[0].length),
        };
    }

    return {
        pageHead: '',
        pageContent: pageSrc,
    };
}

async function discoverComponentNames(componentsDirs) {
    const names = new Set();
    for (const dir of componentsDirs) {
        let entries;
        try {
            entries = await fs.readdir(dir);
        } catch {
            // Directory may not exist (e.g. an optional module dir) — skip silently
            continue;
        }
        for (const f of entries) {
            if (f.toLowerCase().endsWith('.html')) {
                names.add(f.slice(0, -'.html'.length).toLowerCase());
            }
        }
    }
    return names;
}

/**
 * Render a single page to HTML.
 *
 * @param {object} opts
 * @param {string} opts.layoutsDir
 * @param {string} opts.pagePath
 * @param {string|string[]} opts.componentsDir - Single dir or array of dirs.
 *        When an array, each is tried in order when resolving a component tag,
 *        enabling module systems to contribute partials from multiple roots.
 * @param {string} [opts.title]
 * @param {string|null} [opts.jsSrc]
 * @param {string|null} [opts.cssHref]
 * @param {object} [opts.siteConfig] - siteConfig.codeRoot (relative to root) is where <code-file> reads;
 *        siteConfig.highlight turns on build-time syntax highlighting (needs shiki).
 * @param {string} [opts.root] - Project root, for resolving codeRoot. Defaults to process.cwd().
 */
export async function renderPage({ layoutsDir, pagePath, title, jsSrc, cssHref, componentsDir, siteConfig = {}, root }) {
    const componentsDirs = toComponentsDirArray(componentsDir);
    const codeRoot = resolveCodeRoot(siteConfig, root);
    const highlight = Boolean(siteConfig?.highlight);

    const pageSrcRaw = await fs.readFile(pagePath, 'utf-8');
    const { layoutName, title: layoutTitle, pageContent: afterLayoutTag } = extractLayoutDeclaration(pageSrcRaw);

    const { pageHead, pageContent: pageSrcStripped } = extractPageHead(afterLayoutTag);

    const layoutPath = path.join(layoutsDir, `${layoutName}.html`);
    const layoutSrcRaw = await fs.readFile(layoutPath, 'utf-8');

    const componentNames = await discoverComponentNames(componentsDirs);

    let pageSrc = await expandComponents(pageSrcStripped, { componentsDirs, componentNames, codeRoot, highlight });
    pageSrc = await expandTemplateSrc(pageSrc, path.dirname(pagePath));
    const layoutSrc = await expandComponents(layoutSrcRaw, { componentsDirs, componentNames, codeRoot, highlight });

    // [[title]] resolves to "PageTitle | " (with separator) or "" (empty);
    // the layout provides its own suffix in the <title> tag, e.g.
    // <title>[[title]][[siteName]]</title>
    const pageTitle = layoutTitle ?? title ?? '';
    const titlePrefix = pageTitle ? `${pageTitle} | ` : '';

    let result = layoutSrc
        .replaceAll('[[title]]', htmlEscape(titlePrefix))
        .replaceAll('[[cssHref]]', cssHref ? `<link rel="stylesheet" href="${htmlEscape(cssHref)}" />` : '')
        .replaceAll('[[jsSrc]]', jsSrc ? `<script src="${htmlEscape(jsSrc)}" type="module" defer></script>` : '')
        .replaceAll('[[pageHead]]', pageHead)
        .replace('[[app]]', pageSrc);

    for (const [key, value] of Object.entries(siteConfig)) {
        result = result.replaceAll(`[[${key}]]`, htmlEscape(String(value ?? '')));
    }

    // [[moduleAdminNav]] — inject admin nav links from modules that declare adminNav
    if (result.includes('[[moduleAdminNav]]')) {
        const modules = Array.isArray(siteConfig.modules) ? siteConfig.modules : [];
        const navLinks = modules
            .filter(mod => typeof mod === 'object' && mod.defaults?.adminNav)
            .map(mod => {
                const nav = mod.config?.adminNav ?? mod.defaults.adminNav;
                if (nav === false) return ''; // configure(mod, { adminNav: false }) disables it
                return `<a data-nav-section="${htmlEscape(nav.path)}" href="${htmlEscape(nav.path)}" data-requires-permission="${htmlEscape(nav.permission || 'admin.access')}" hidden class="hover:text-slate-300">${htmlEscape(nav.label)}</a>`;
            })
            .filter(Boolean)
            .join('\n                    ');
        result = result.replaceAll('[[moduleAdminNav]]', navLinks);
    }

    result = result.replaceAll('[[gitSha]]', getGitSha());

    return result;
}
