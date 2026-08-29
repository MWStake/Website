# Scribunto (Lua) modules for dev.mwstake.org

This directory holds a portable dump of the wiki's `Module:` namespace so the
Lua modules can be re-created on a fresh dev instance without scraping
production by hand.

## What is here
- `modules.xml` — MediaWiki XML dump of every page in namespace 828
  (`Module:`), exported with **current revisions only** and content model
  `Scribunto`. 60 pages, including the two modules production mwstake.org is
  missing (`Module:Submit an edit request/config` and `Module:Template
  invocation`, which were imported from en.wikipedia.org).
- `import-modules.sh` — imports `modules.xml` into the web container and
  forces the Scribunto content model on every `Module:` page.
- `fix-scribunto-model.php` — maintenance script (run inside the container)
  that flips any `Module:` page still stored as `wikitext` to `Scribunto`.

## Reproducible import
```
cd content/mwstake
./import-modules.sh            # uses container mwstake-web-1 by default
./import-modules.sh other-web  # or pass a different web container name
```
Then purge the parser cache of any page that uses the modules (or restart the
web + varnish containers), otherwise stale "No such module" errors stay cached:
```
podman restart mwstake-web-1 mwstake-varnish-1
```

## Verify
```
curl -sS "https://dev.mwstake.org/w/api.php?action=parse&title=ZzTest\
&text=%7B%7B%23invoke%3AClickable%20button%202%7C%7D%7D&contentmodel=wikitext&prop=text&format=json"
```
The result must NOT contain `No such module`.

## Why this is necessary (gotchas)
1. The standard page import (Special:Export / importDump) does **not** include
   the `Module:` namespace by default, so a fresh dev wiki has zero Lua modules
   and every `{{#invoke:...}}` fails with *Script error: No such module*.
2. Production's `Special:Export` serializes modules with content model
   `wikitext` (a known quirk), so a straight `importDump.php` imports them as
   wikitext and Scribunto still rejects them. `fix-scribunto-model.php` (run by
   the import script) corrects the content model.
3. `Module:Submit an edit request` (a Wikipedia module) `require`s
   `Module:Submit an edit request/config`, which does **not** exist on
   production mwstake.org. It was imported from en.wikipedia.org; re-add it
   when regenerating.

## Regenerating modules.xml from production
```
# on the dev host:
python3 - <<'PY'
import urllib.request, urllib.parse, json
api = "https://dev.mwstake.org/w/api.php"   # or https://mwstake.org/w/api.php
titles = []
c = {}
while True:
    q = {"action":"query","list":"allpages","apnamespace":828,"aplimit":500,"format":"json",**c}
    d = json.load(urllib.request.urlopen(api+"?"+urllib.parse.urlencode(q), timeout=60))
    titles += [p["title"] for p in d["query"]["allpages"]]
    if "continue" not in d: break
    c = d["continue"]
data = urllib.parse.urlencode({"pages":"\n".join(titles),"curonly":"1"}).encode()
req = urllib.request.Request("https://dev.mwstake.org/wiki/Special:Export", data=data, method="POST")
open("modules.xml","wb").write(urllib.request.urlopen(req, timeout=200).read())
PY
```
Then re-add the two Wikipedia-only modules (`Submit an edit request/config`,
`Template invocation`) and run `./import-modules.sh`.
