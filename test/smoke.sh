#!/bin/sh
# Smoke test against the Playground site. Start WordPress first:
#   npx @wp-playground/cli@latest server --port=9410 --mount=./webosaurus-hot-take-box:/wordpress/wp-content/plugins/webosaurus-hot-take-box --mount=./test:/seed --blueprint=./test/blueprint.json
# Then: sh test/smoke.sh
B=${B:-http://127.0.0.1:9410}
T=$(mktemp -d); J=$T/jar
fail=0
get() { curl -s -L -b "$J" -c "$J" "$B$1"; }
api() { m=$1; p=$2; d=$3; curl -s -b "$J" -c "$J" -H "X-WP-Nonce: $N" -H 'Content-Type: application/json' -X "$m" "$B/wp-json/whtb/v1$p" ${d:+-d "$d"}; }
ok()  { echo "ok   $1"; }
bad() { echo "FAIL $1"; fail=1; }
has() { if grep -q -- "$2" "$1"; then ok "$3"; else bad "$3"; fi; }
hasnt() { if grep -q -- "$2" "$1"; then bad "$3"; else ok "$3"; fi; }

get / > /dev/null
get '/wp-admin/admin.php?page=whtb-desk&tab=all' > $T/desk
N=$(grep -oE 'createNonceMiddleware\( "[a-f0-9]+"' $T/desk | grep -oE '[a-f0-9]{10}')
has $T/desk 'whtb-desk--row' 'desk lists posts'
hasnt $T/desk 'Fatal error' 'desk has no PHP errors'
ID=$(get '/?p=4' > /dev/null; echo 4)

# Seeded live take renders as plain HTML at the end of the post.
get /dark-mode/ > $T/dark
has $T/dark 'class="whtb whtb--nojs"' 'live box renders'
has $T/dark 'Dark mode: because even your website deserves a night shift.' 'take text is in raw HTML'
has $T/dark 'lottie_light.min.js' 'lottie player enqueued'
has $T/dark 'websaurus-smug.json' 'mood lottie url present'
get /chai-code-burnout/ > $T/chai
hasnt $T/chai 'class="whtb' 'no box without an approved take'
hasnt $T/chai 'lottie_light.min.js' 'no scripts without a box'

# Draft through the mocked Claude API.
api POST /posts/4/draft '{}' > $T/draft
has $T/draft '"status":"pending"' 'draft parks takes as pending'
has $T/draft 'anthropic:claude-opus-5-5' 'engine recorded'
has $T/draft 'site map to my heart, Google approved' 'dashes stripped from drafts'
get '/?whtb_peek=1' > $T/req
has $T/req '"anthropic-version": "2026-10-21"' 'anthropic-version header'
has $T/req '"anthropic-beta": "server-side-fallback-2026-07-01"' 'fallback beta header'
has $T/req '"fallbacks": "default"' 'fallbacks default'
has $T/req '"effort": "low"' 'low effort'
has $T/req 'Why every website needs a sitemap' 'article title sent'
has $T/req 'Submit it in Search Console' 'article body sent'
hasnt $T/req 'temperature' 'no temperature for Claude'

# Unsaved editor text overrides the stored post.
api POST /posts/4/draft '{"title":"Unsaved headline about chai","content":"<p>fresh words</p>"}' > /dev/null
get '/?whtb_peek=1' > $T/req2
has $T/req2 'Unsaved headline about chai' 'editor override used'

# Not live until approved.
get /?p=4 > $T/p4
hasnt $T/p4 'class="whtb' 'pending take is not public'
api POST /posts/4/approve '{"takes":[{"text":"Kal ho na ho, sitemap zaroor ho.","mood":"hype"}]}' > $T/appr
has $T/appr '"status":"live"' 'approve goes live'
api POST /posts/4/approve '{"takes":[{"text":"  "}]}' > $T/empty
has $T/empty 'whtb_empty' 'blank approve rejected'
api POST /posts/4/toss '{"index":0}' > $T/toss
has $T/toss '"status":"live"' 'toss keeps live take'
api POST /posts/4/pull '{}' > $T/pull
has $T/pull '"status":"pending"' 'pull takes it down'

# Offline engine when the provider has no key.
api POST /posts/5/draft '{}' > /dev/null
get '/wp-admin/admin.php?page=whtb-settings' > $T/settings
has $T/settings 'Saved, ends in real' 'settings show masked key'
hasnt $T/settings 'sk-test-not-real' 'settings never print the key'

# Shortcode.
has $T/settings 'webosaurus_hot_take' 'shortcode documented in settings'

rm -rf "$T"
[ $fail = 0 ] && echo "ALL GOOD" || { echo "SOME CHECKS FAILED"; exit 1; }
