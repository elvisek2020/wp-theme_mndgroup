#!/usr/bin/env python3
"""Minimal WordPress REST client for www.mndgroup.eu (drafts only).

Credentials are read from ../ctime.txt (section "WordPress API"); they are never printed:
  WordPress API
  Uživatel: <login of an Editor account>
  Heslo aplikace: <application password from Users → Profile → Application passwords>

Usage:
  wp.py whoami
  wp.py pages [--search X]           # pages in all languages (Polylang)
  wp.py slides                       # homepage banners
  wp.py posts [--search X]
  wp.py drafts [--type page]
  wp.py draft --title "..." --content file.html [--type page] [--excerpt "..."] [--slug x] [--lang cs|en] [--featured image.webp] [--id 123]
  wp.py upload image.jpg [--alt "..."]
  wp.py get 123 [--type page]        # raw content to stdout
  wp.py media [--search X]
Content is always created/updated with status=draft; publishing is left to a human.
Set WP_BASE=http://localhost:8321/wp-json/wp/v2 (and WP_CTIME=<file>) to work against the local copy.
"""
import argparse, base64, html, json, mimetypes, os, re, sys, urllib.error, urllib.request

BASE = os.environ.get('WP_BASE', 'https://www.mndgroup.eu/wp-json/wp/v2').rstrip('/')
ADMIN = BASE.split('/wp-json/')[0] + '/wp-admin'
CTIME = os.environ.get('WP_CTIME') or os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', 'ctime.txt')
TYPES = {'post': '/posts', 'page': '/pages', 'slide': '/slide'}


def _auth() -> str:
    try:
        text = open(CTIME, encoding='utf-8').read()
        block = text[text.index('WordPress API'):]
        user = re.search(r'Uživatel:\s*(\S+)', block).group(1)
        pw = re.search(r'Heslo aplikace:\s*(.+)', block).group(1).strip()
    except (OSError, ValueError, AttributeError):
        sys.exit('Chybí ctime.txt se sekcí "WordPress API" (Uživatel, Heslo aplikace) – viz nápověda wp.py -h.')
    return 'Basic ' + base64.b64encode(f'{user}:{pw}'.encode()).decode()


def api(method, path, data=None, raw=None, headers=None):
    h = {'Authorization': _auth(), 'User-Agent': 'mndgroup-wp-cli'}
    body = None
    if raw is not None:
        body = raw
    elif data is not None:
        body = json.dumps(data).encode()
        h['Content-Type'] = 'application/json'
    h.update(headers or {})
    req = urllib.request.Request(BASE + path, data=body, method=method, headers=h)
    try:
        with urllib.request.urlopen(req, timeout=60) as r:
            return json.loads(r.read() or b'null')
    except urllib.error.HTTPError as e:
        msg = e.read().decode('utf-8', 'replace')
        try:
            msg = json.loads(msg).get('message', msg)
        except Exception:
            pass
        sys.exit(f'HTTP {e.code}: {msg[:300]}')


def upload(path, alt=''):
    name = os.path.basename(path)
    mime = mimetypes.guess_type(name)[0] or 'application/octet-stream'
    media = api('POST', '/media', raw=open(path, 'rb').read(), headers={
        'Content-Type': mime, 'Content-Disposition': f'attachment; filename="{name}"'})
    if alt:
        api('POST', f"/media/{media['id']}", {'alt_text': alt})
    return media


def listing(path, query=''):
    q = '?per_page=100&_fields=id,date,modified,slug,title,link,status,lang,menu_order,media_details,source_url' + query
    page, rows = 1, []
    while True:
        batch = api('GET', f'{path}{q}&page={page}')
        rows += batch
        if len(batch) < 100:
            break
        page += 1
    return rows


def main():
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    sub = ap.add_subparsers(dest='cmd', required=True)
    sub.add_parser('whoami'); sub.add_parser('slides')
    for name in ('pages', 'posts', 'media'):
        sub.add_parser(name).add_argument('--search', default='')
    dr = sub.add_parser('drafts'); dr.add_argument('--type', choices=TYPES, default='post')
    g = sub.add_parser('get'); g.add_argument('id', type=int); g.add_argument('--type', choices=TYPES, default='post')
    u = sub.add_parser('upload'); u.add_argument('file'); u.add_argument('--alt', default='')
    d = sub.add_parser('draft')
    d.add_argument('--title', required=True); d.add_argument('--content', required=True)
    d.add_argument('--type', choices=TYPES, default='post'); d.add_argument('--excerpt', default='')
    d.add_argument('--slug', default=''); d.add_argument('--lang', default='')
    d.add_argument('--featured'); d.add_argument('--id', type=int)
    a = ap.parse_args()

    if a.cmd == 'whoami':
        me = api('GET', '/users/me?context=edit')
        print(json.dumps({'id': me['id'], 'name': me['name'], 'roles': me.get('roles')}, ensure_ascii=False))
    elif a.cmd in ('pages', 'posts', 'slides'):
        path = {'pages': '/pages', 'posts': '/posts', 'slides': '/slide'}[a.cmd]
        query = '&lang=&status=publish,draft,pending,private&context=edit'
        if getattr(a, 'search', ''):
            query += '&search=' + urllib.request.quote(a.search)
        if a.cmd == 'slides':
            query += '&orderby=menu_order&order=asc'
        rows = listing(path, query)
        for r in rows:
            title = html.unescape(r['title'].get('raw') or r['title'].get('rendered', ''))
            print(f"{r['id']:>6}  {r['modified'][:10]}  {r.get('lang', ''):<3} {r['status']:<8} {title}")
        print(f'# {len(rows)} items', file=sys.stderr)
    elif a.cmd == 'media':
        rows = listing('/media', '&search=' + urllib.request.quote(a.search) if a.search else '')
        for r in rows:
            md = r.get('media_details') or {}
            print(f"{r['id']:>6}  {r['date'][:10]}  {md.get('width', '?')}x{md.get('height', '?')}  {r['source_url'].split('/uploads/')[-1]}")
        print(f'# {len(rows)} items', file=sys.stderr)
    elif a.cmd == 'drafts':
        for p in api('GET', f'{TYPES[a.type]}?status=draft&per_page=50&context=edit&lang='):
            print(f"{p['id']:>6}  {p['modified'][:16]}  {p['title']['raw']}")
    elif a.cmd == 'get':
        print(api('GET', f'{TYPES[a.type]}/{a.id}?context=edit')['content']['raw'])
    elif a.cmd == 'upload':
        m = upload(a.file, a.alt); print(m['id'], m['source_url'])
    elif a.cmd == 'draft':
        post = {'title': a.title, 'content': open(a.content, encoding='utf-8').read(), 'status': 'draft'}
        if a.excerpt: post['excerpt'] = a.excerpt
        if a.slug: post['slug'] = a.slug
        if a.lang: post['lang'] = a.lang
        if a.featured: post['featured_media'] = upload(a.featured, a.title)['id']
        path = TYPES[a.type]
        if a.id:
            cur = api('GET', f'{path}/{a.id}?context=edit')
            if cur['status'] != 'draft':
                sys.exit('Refusing to modify a non-draft item.')
            res = api('POST', f'{path}/{a.id}', post)
        else:
            res = api('POST', path, post)
        print(res['id'], res['status'], f"{ADMIN}/post.php?post={res['id']}&action=edit")


if __name__ == '__main__':
    main()
