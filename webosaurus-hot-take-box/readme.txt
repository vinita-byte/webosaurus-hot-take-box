=== Webosaurus Hot Take Box ===
Contributors: whattheweb
Tags: ai, engagement, mascot, lottie, puns
Requires at least: 6.2
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

At the end of every post, WebSaurus the T rex drops a one line punny reaction. AI drafts the takes, you approve the one that goes live.

== Description ==

Big brands in India run a topical billboard the morning after every big story: one witty line, one mascot, everybody grins. Hot Take Box brings that ritual to your blog.

* **AI drafts, humans approve.** When a post is published, the AI reads the whole article and drafts a handful of punny one liners. They wait on the Hot Take Desk until an editor approves one. Nothing reaches readers without approval.
* **Fresh every time.** Each batch picks random comedy angles (Bollywood, cricket, dinosaur puns, food stall menu boards, deadpan headlines and more) plus a random seed, so Draft again really means new jokes.
* **An animated mascot.** WebSaurus, the red T rex in nerd glasses, reacts in seven moods: smug, hype, cackling, shocked, thinking, talk and idle. Readers can poke him to make him cackle.
* **Bring your own AI.** Anthropic Claude, OpenAI, Google Gemini or Groq. No key yet? The offline pun engine writes takes from the article keywords with no network calls at all.
* **Rotation.** Approve several takes for one post and show a random one on each visit.
* **Readable by everyone.** The take is plain HTML at the end of the post, so it works without JavaScript and is visible to search engines and AI crawlers. The Lottie animation loads only when the box scrolls into view and respects reduced motion.
* **House style built in.** Optional cleanup strips em dashes, en dashes and hyphenated words from every draft.

= The approval flow =

1. Publish a post. Takes are drafted in the background.
2. Open **Hot Takes > Desk**, or the Hot Take Box panel under the post editor.
3. Edit a line if you like, pick a mood, press **Approve**.
4. The box appears at the end of the post. Press **Pull** any time to take it down.

Authors can draft and toss takes. Only users who can publish the post can approve or pull one.

= Shortcode =

The box is added after the post content automatically. Turn that off in Settings and place it yourself with `[webosaurus_hot_take]`, or `[webosaurus_hot_take id="123"]` for a specific post.

= For developers =

* `whtb_pre_generate` filter: return an array of takes (`text`, `mood`) to plug in your own writer.
* `whtb_takes` filter: change the approved takes before they render.
* Keys can live in wp-config.php: `WHTB_ANTHROPIC_KEY`, `WHTB_OPENAI_KEY`, `WHTB_GEMINI_KEY`, `WHTB_GROQ_KEY`.
* Style it with CSS custom properties on `.whtb`: `--whtb-accent`, `--whtb-ink`, `--whtb-paper`, `--whtb-bubble`, `--whtb-mascot`.

== External services ==

When you choose an AI provider and save its API key, the plugin sends the post title and plain text content to that provider each time takes are drafted (on publish, or when you press Draft). Nothing is sent with the offline pun engine, and nothing is ever sent from a visitor's browser.

* Anthropic: https://www.anthropic.com/legal/privacy
* OpenAI: https://openai.com/policies/privacy-policy
* Google Gemini: https://policies.google.com/privacy
* Groq: https://groq.com/privacy-policy

== Installation ==

1. Upload the `webosaurus-hot-take-box` folder to `/wp-content/plugins/`, or install the zip from Plugins > Add New > Upload.
2. Activate it.
3. Go to Hot Takes > Settings, pick a provider and paste a key (or keep the offline pun engine).

== Frequently Asked Questions ==

= Does anything go live without my approval? =

No. Drafts are private post meta. Only approved takes render.

= Will it slow my site down? =

The box is a few lines of HTML. The animation script and the mascot file load only on posts with a live take, deferred, and only once the box scrolls into view.

= What happens if I edit the post after drafting? =

The desk flags the drafts as stale so you can draft a fresh batch.

== Credits ==

WebSaurus Lottie animations: https://websaurus-lottie.vercel.app
Lottie player: lottie-web 5.12.2 by Airbnb, MIT license.

== Changelog ==

= 1.0.0 =
* First release.
