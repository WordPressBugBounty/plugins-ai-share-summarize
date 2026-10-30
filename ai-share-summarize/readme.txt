=== Share Buttons & AI-powered Summaries ===
Contributors: fernandot,ayudawp
Tags: claude, chatgpt, social share, ai, perplexity
Requires at least: 6.1
Tested up to: 7.1
Stable tag: 2.7.1
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Inline AI summary on every post + one-click sharing to social networks and 11 AI assistants. Powered by the new WordPress 7.0 AI Connectors.

== Description ==

**Share Buttons & AI-powered Summaries** turns every post into an AI-aware destination. It generates an **inline AI summary** readers can expand next to the share buttons, and offers one-click sharing to every major social network and the leading AI assistants.

Among the **first plugins to integrate the native WordPress 7.0 AI Connectors API**: configure your AI provider once in **Settings > Connectors** (OpenAI, Anthropic, Google) and the plugin reuses those credentials — no API keys to manage, no extra accounts.

= Inline AI Summary =

* **Two-tier cascade**: Level A uses the WordPress 7.0 AI Client; Level C is a built-in PHP extractive fallback with zero API cost on any WP 6.1+ install.
* **Choose your AI model and mode**: pick the provider and model used to generate summaries, or leave it on Automatic to use a fast, cost-effective model instead of the newest, most expensive one. Set how generation behaves too: AI with extractive fallback, AI only, extractive only, or disabled.
* **Paragraph or bullet-list format**: show the summary as prose (one paragraph per sentence) or as a concise bullet list, with configurable length (1-5 sentences or points).
* **Collapsible inline block** with native `<details>`, `data-nosnippet` and Schema.org `CreativeWork` microdata, so Google leaves the summary out of its snippets and AI Overviews and it never competes with your own text.
* **Editor controls** in the block sidebar and the classic meta box: view, edit manually, regenerate on demand, hide per post (independently from the buttons).
* **Visitor-facing "Generate AI summary" button** for posts without a stored summary, restrictable to extractive-only or open to the AI Client.
* **Your own content inside the box**: anything you want below the summary, as text, HTML or scripts (an ad, a newsletter form, a map, a notice), loaded when a reader opens the box, with an optional label.
* **Only where there is something to summarize**: posts with fewer than 50 words of text get no summary, so the box never shows an AI reply saying there was nothing to summarize.
* **Async generation via WP-Cron**. `[ayudawp_aiss_summary]` shortcode also available.

= Sharing =

* **Social networks**: X (Twitter), LinkedIn, Facebook, Telegram, WhatsApp, Email, Raindrop, Reddit, Bluesky, LINE, Mastodon, Threads, Pinterest.
* **AI assistants**: Claude, ChatGPT, Google AI, Gemini, Grok, Perplexity, DeepSeek, Mistral, Copilot, Qwen, Meta AI — each opens with a citation-ready prompt linking back to your URL.
* **Browser actions**: a **Copy link** button and a **native Share** button that opens the device share sheet (reaching WhatsApp, Signal, Instagram, Messages or AirDrop), shown only where the browser supports it.
* **Google Preferred Source**: a button that takes readers to the Google tool where they mark your site as a preferred source, with the clicks counted per post. It can go with the social networks, in a section of its own, or anywhere with the shortcode.
* **Where they show**: inline before or after the content, through the `[ayudawp_share_buttons]` shortcode, or in a **floating bar** pinned to the left, the right or the bottom of the screen — on its own or alongside the inline block.
* **Compact on phones**: optionally collapse the whole set behind a single Share button, or show a few buttons plus a More button, without touching the desktop layout.
* 6 visual styles, 4 sizes, custom colors, brand SVG icons, dark-mode auto-adaptation, drag-and-drop ordering, optional UTM tagging. SEO-friendly (`<a rel="nofollow">` or `<button>`, auto-exclusion on noindex content for all major SEO plugins).

= Analytics =

* Click tracking per platform and per post, timeline chart with per-platform breakdown, period comparison (previous period, same period last year, custom range), CSV export.
* Dashboard widget with 7-day sparkline. [VigIA](https://wordpress.org/plugins/vigia/) cross-reference (clicks vs. AI crawler visits) when active. Redis/Memcached compatible.

= Why use it? =

1. **Cite your source naturally**: when readers expand the summary or share to an AI assistant, your URL travels with the content.
2. **Reach every audience**: full social spectrum plus 11 AI assistants in one place.
3. **Future-proof**: built on WP 7.0 Connectors and a REST API ready for agentic clients.

== Installation ==

= Automatic installation =

1. Go to Plugins > Add New in your WordPress admin
2. Search for "Share Buttons & AI-powered Summaries"
3. Click "Install Now" and then "Activate"
4. Go to Settings > Share Buttons & AI-powered Summaries to configure

= Manual installation =

1. Download the plugin ZIP file
2. Go to Plugins > Add New > Upload Plugin
3. Select the ZIP file and click "Install Now"
4. Activate the plugin
5. Configure in Settings > Share Buttons & AI-powered Summaries

= Complete setup =

1. **Go to Settings > Share Buttons & AI-powered Summaries**
2. **Select enabled buttons**: Choose which social networks and AIs to display
3. **Configure automatic insertion**:
   - Choose position: Before content, after, both, or disabled (shortcode only)
   - Select content types: Posts, pages, products, or any Custom Post Type
4. **Configure SEO integration**: Enable/disable automatic exclusion of noindex content
5. **Customize AI prompt**: Modify the default text if desired (excellent default included)
6. **Configure title**: Change the text before buttons and choose heading style
7. **Set section titles**: Optionally set separate titles for AI and Social groups
8. **Configure X mentions**: Add your X (Twitter) handle for automatic mentions
9. **Set Mastodon instance**: Enter your Mastodon server domain if using Mastodon
10. **Choose visual style**: Minimal, brand colors, outline, dark, custom colors, or icons-only
11. **Set button size**: Compact, normal, large, or fluid
12. **Pick custom colors**: Use the color picker when "Custom colors" style is selected
13. **Configure icons**: Enable icons for text styles or use pure icons-only mode
14. **Set icon shape**: Choose circular or square corners for icons-only buttons
15. **Set button order**: Social first, AI first, or mixed — drag & drop to reorder within each group
16. **Configure SEO settings**: Choose between links with nofollow or button elements
17. **Configure data retention**: Set retention period and optionally delete all data when plugin is uninstalled
18. **Configure the AI Summary** in its own tab:
    - Choose the generation mode (AI with extractive fallback, AI only, extractive only, or disabled) and, optionally, the AI model
    - Choose position (before / after the buttons, before the content, or disabled)
    - Pick the post types where summaries should be generated (fully independent from the buttons' list)
    - Optionally enable the visitor-facing "Generate AI summary" button for posts without a stored summary
    - On WordPress 7.0+, configure your AI provider in **Settings > Connectors** (the plugin reuses those credentials — no API keys to manage here)
19. **Save changes** (the Share Buttons and AI Summary tabs each save their own options)

**Manual insertion with shortcode:**
- Share buttons: `[ayudawp_share_buttons]`
- AI summary block: `[ayudawp_aiss_summary]` or `[ayudawp_aiss_summary post_id="123"]`
- Specific buttons: `[ayudawp_share_buttons buttons="claude,twitter,linkedin,deepseek,mastodon"]`
- Custom style: `[ayudawp_share_buttons style="minimal" show_title="false"]`
- With icons: `[ayudawp_share_buttons show_icons="true" style="brand"]`
- Icons only: `[ayudawp_share_buttons style="icons-only"]`
- Circular icons: `[ayudawp_share_buttons style="icons-only" icon_style="circular"]`
- Custom title: `[ayudawp_share_buttons title_text="Share or Summarize" title_style="h3"]`
- Section titles: `[ayudawp_share_buttons ai_title="Summarize with AI" social_title="Share on social media"]`
- Button size: `[ayudawp_share_buttons size="compact"]`
- Outline style: `[ayudawp_share_buttons style="outline" show_icons="true"]`

**Per-post controls (block editor sidebar / classic meta box):**
- Hide share buttons on this specific post
- Hide the AI summary on this specific post (independent from the buttons control)
- View, edit and regenerate the AI summary on demand
- Manually-edited summaries are locked against auto-regeneration

== Frequently Asked Questions ==

= Is this plugin completely free? =

Yes, Share Buttons & AI-powered Summaries is 100% free with all features included, including support for all social networks, AI platforms, and the full analytics dashboard with CSV export.

= How does the inline AI summary work? =

When you publish or update a post, the plugin generates a short summary asynchronously (in a background WP-Cron event, to avoid blocking the editor save). The summary then appears inline next to the share buttons, inside a collapsible block. Visitors can expand it without leaving the page. You can disable the feature globally in **Settings > Share Buttons & AI-powered Summaries > AI Summary**, choose where the summary is placed (before/after the buttons, or before the content), and override or edit the text per post from the editor sidebar.

= Why does a short post have no summary? =

A post needs at least 50 words of text. Images, embeds, captions and tables do not count.

* Below that, the AI tends to answer that there is nothing to summarize, and that answer would end up in the box.
* So no summary is generated and the visitor button does not appear.
* A summary you write by hand in the editor is always shown.
* The editor tells you when a post is below the minimum.
* Developers can change the minimum with the `ayudawp_aiss_summary_min_words` filter (0 turns it off).

= Can I add my own content or ads inside the summary box? =

Yes, in **Settings > Share Buttons & AI-powered Summaries > AI Summary**, under Custom content inside the box.

* Anything fits: text, HTML or scripts, such as an ad, a newsletter form, a map or a notice.
* It goes below the summary, on the post itself, and only loads when a reader opens the box.
* You can also leave it empty and fill the element with id `ayudawp-aiss-custom` from an ad server or Google Tag Manager. The `ayudawp_aiss_custom_visible` event, also pushed to the dataLayer, tells a tag when the box opens.
* Scripts that queue their work (AdSense, Google Ad Manager, Tag Manager) work. Scripts that use document.write do not.
* An optional label goes above it: Advertisement, or your own text.
* Only users who can publish unfiltered HTML can change it. On a network, that is the super admin.

= How do I choose the AI generation mode and model? =

The **AI Summary generation** setting is a single selector with four modes: *AI with extractive fallback* (recommended), *AI only*, *Extractive only* (never contacts a provider), and *Disabled*. The **AI model** selector lists the models your configured providers expose; leave it on *Automatic* to let the plugin pick a fast, cost-effective model per provider instead of the newest, most expensive flagship.

= Where do I configure API keys for the AI summary? =

You don't configure keys in this plugin. The summary feature relies on the WordPress 7.0 **AI Client** introduced in core, which manages credentials centrally in **Settings > Connectors**. Configure your preferred provider (OpenAI, Anthropic, Google) there once and every plugin on the site — including this one — uses those credentials.

= What happens on WordPress versions older than 7.0? =

On WP < 7.0 (or 7.0+ without a Connector configured), the plugin falls back to a PHP extractive summarizer: it picks the most representative sentences from your content based on word frequency. The result is labelled "Basic summary" in the frontend so readers know it isn't AI-generated. You can disable the fallback if you only want AI-quality summaries.

= Why does my AI summary say "Basic summary"? =

That tag appears when the summary was produced by the PHP extractive fallback instead of the WP AI Client. This happens when WordPress 7.0 is not installed, when no AI Connector is configured in **Settings > Connectors**, or when the AI provider call returned an error. Configure a Connector and republish the post to get an AI-generated summary; the "Basic summary" label disappears automatically.

= Is the post content sent to a third-party AI service? =

Only when the WP AI Client integration is active. In that case, the post content (title plus up to ~3000 characters of body text) is sent to whatever provider you configured in **Settings > Connectors** (OpenAI, Anthropic or Google). The PHP extractive fallback runs entirely on your server and never sends data anywhere. You can disable AI generation completely in the plugin settings to keep everything local.

= AI generation worked before, now everything falls back to "Basic summary". What changed? =

If you have the canonical **AI** plugin from wordpress.org installed, go to **Tools > Connector Approvals** and make sure the toggle next to "Share Buttons & AI-powered Summaries" is enabled for your provider. That plugin's approval system intercepts outbound AI requests and silently blocks any plugin it hasn't approved yet. When this is the likely cause, the plugin's settings page surfaces a direct link to the approvals screen alongside the standard error message.

= How does the analytics dashboard work? =

The analytics dashboard tracks every button click on your site, showing you which platforms are most popular and which content gets the most engagement. You can filter by date range (7 to 365 days or custom dates), compare with a previous period, the same period last year, or custom dates, and view data in timeline charts, platform tables, and content performance tables. In the platforms table, the click count of each button opens the posts it was clicked from. Stat cards show percentage changes when comparison is active. You can download data as CSV files from the export dropdown. A dashboard widget provides a quick 7-day summary right on your WordPress admin homepage.

= How do I export analytics data? =

In the Analytics tab, use the date filter to select the period you want, then click the "Export CSV" button to open the export menu. Choose "Current period" for a full breakdown (date, platform, type, post title, URL, clicks) or "Timeline summary" for a daily totals export. When period comparison is active, the timeline export automatically includes comparison columns (date, clicks, comparison date, comparison clicks, difference, change percentage). Files download immediately and can be opened in Excel, Google Sheets, or any spreadsheet application.

= What is VigIA and how does the integration work? =

[VigIA](https://wordpress.org/plugins/vigia/) is a free WordPress plugin by AyudaWP that monitors AI crawler visits to your site — tracking bots like GPTBot (ChatGPT), ClaudeBot, PerplexityBot and 50+ more. When VigIA is active alongside Share Buttons & AI-powered Summaries, the analytics dashboard shows an additional panel where you can cross-reference your share button clicks with AI crawler activity. This lets you see, for example, whether a spike in Claude clicks correlates with increased ClaudeBot crawling of that content. You can install VigIA directly from the plugin screen.

= Will it slow down my website? =

No. The plugin is ultra-optimized with a modular structure and lightweight SVG icons that load instantly. Frontend CSS and JavaScript only load on pages where the share buttons or the AI summary actually display — posts where both are hidden via the meta box do not load any plugin assets.

= How do I hide buttons on specific pages? =

Edit the post or page where you want to hide buttons, find the "Share Buttons & AI-powered Summaries" meta box in the sidebar, and check "Hide share buttons on this content". The AI summary has its own independent checkbox ("Hide AI summary on this content"), so you can hide either feature — or both — on any post. This works with both the classic editor and the block editor.

= Can I automatically hide buttons on noindex content? =

Yes! The plugin integrates with major SEO plugins (Yoast, Rank Math, All in One SEO, SEOPress, The SEO Framework, Visibility). Enable the "Exclude noindex content" option in settings to automatically hide buttons on content marked as noindex.

= What's the difference between Google AI and Gemini buttons? =

**Google AI** uses the new AI Mode available in most countries, launching the AI response automatically with your prompt. **Gemini** uses the traditional method (copy prompt and open Gemini) which still works everywhere. Choose based on availability in your region or user preference.

= Should I use links or buttons for SEO? =

Both options are valid:
- **Links with nofollow (default)**: Better user experience, allows right-click to copy link, works without JavaScript
- **Button elements**: Not counted as links by search engines, cleaner link profile, reduces crawl budget usage

The nofollow attribute already prevents PageRank transfer, so the SEO impact is minimal. Choose based on your specific needs.

= How do the section titles work? =

You can set separate headings for the AI and Social button groups. When either section title is filled in, the general title disappears and each group gets its own heading. If both section titles are left empty, the general title works as before. When using the "Mixed" button order, the general title is always displayed regardless of section titles, since both groups are interleaved. You can also set section titles via shortcode: `[ayudawp_share_buttons ai_title="Summarize" social_title="Share"]`

= How does Mastodon sharing work? =

Since Mastodon is a federated network, the share link needs to point to your specific instance. Set your Mastodon server domain (e.g. mastodon.social, fosstodon.org) in the plugin settings. The share button will then open the compose screen on your instance with the post title and URL pre-filled.

= Can I customize the visual appearance? =

Yes, you can choose from 6 predefined styles:
- **Minimal (default)**: Clean transparent design with borders
- **Brand**: Each platform's characteristic colors
- **Outline**: Brand-colored borders with transparent background, fills on hover
- **Dark**: Optimized for dark backgrounds
- **Custom colors**: Pick your own background and text colors with a color picker
- **Icons-only**: Modern style showing only platform icons

= Can I change the button size? =

Yes, four sizes are available:
- **Compact**: Smaller buttons for tight layouts
- **Normal**: Standard size (default)
- **Large**: Bigger buttons for more prominent display
- **Fluid**: Buttons expand to fill available container width

= How do the icons work? =

The icon system uses official brand SVG paths from Simple Icons where available, ensuring accurate and recognizable platform logos. Options include:
- **Icons with text**: Add icons to the left of button text in any style
- **Icons-only**: Show only icons in circular or square buttons
- **Smart tooltips**: Platform names appear when hovering over any button
- **Responsive**: Icons automatically adapt to screen size

= Can I change the title before the buttons? =

Yes, you can:
- Change the text to anything you want
- Choose the HTML element: h3, h4, h5, h6, or span
- Hide the title completely by leaving the text field empty
- Set separate titles for AI and Social groups

= How does automatic insertion work? =

You have complete control:
- **Before content**: Buttons appear before the post text
- **After content**: Buttons appear after the post text  
- **Both positions**: Buttons in both locations
- **Disabled**: Use only shortcode `[ayudawp_share_buttons]`

Plus, you choose exactly which content types display buttons.

= Can I control which content types show buttons? =

Absolutely. You can select from all post types registered in your WordPress:
- Posts, pages, WooCommerce products
- Custom Post Types (portfolios, testimonials, etc.)
- Complete per content type control

= How do I customize the AI prompt? =

In Settings > Share Buttons & AI-powered Summaries you'll find:
- **Default prompt**: Pre-optimized for best results with citation
- **Custom text field**: Add personalized text to all AI prompts
- **X handle**: Configure automatic mentions in social shares
- The plugin automatically adds your content URL as source

= What happens if an AI changes its URL? =

We continuously monitor and update all AI links to ensure they work correctly. The plugin receives regular updates to maintain compatibility.

= Can I use only specific buttons? =

Yes, with the shortcode you can display only the buttons you want:
`[ayudawp_share_buttons buttons="claude,chatgpt,deepseek,twitter,mastodon,threads"]`

= Is it GDPR compliant? =

Yes. The plugin does not collect any personal user data. The analytics system only records anonymous click events — platform name, post ID, and date — with no user identification, IP address, or session data stored at any point. Analytics data is retained in your own database for as long as you keep the plugin active. If you uninstall the plugin with the "Delete all plugin data" option enabled, all analytics records are permanently removed along with the rest of the plugin data.

= How does Google AI integration work? =

Google AI has direct integration:
- Clicking the button opens Google's AI Mode with your prompt
- The AI automatically processes the prompt and shows results
- Works in most countries where AI Mode is available

= How does LINE integration work? =

LINE is extremely popular in Asian markets (Japan, Taiwan, Thailand). The plugin allows sharing your content directly to LINE, helping you reach millions of users in these regions.

= Why does Google AI respond in English even though my site is in another language? =

Google AI Mode has a default behavior of responding in English regardless of browser or site language. To get responses in your preferred language, add a custom instruction in the plugin settings.

Go to Settings > Share Buttons & AI-powered Summaries > "Custom text in prompts" and add:
`Deliver the response in Spanish` (or your preferred language)

This instruction will be added to all AI prompts, ensuring responses match your language preference.

= How does Gemini integration work? =

Gemini has a special behavior:
- Clicking the button automatically copies the prompt to clipboard
- Opens Gemini in a new tab
- User can paste the prompt directly and get the summary

= How do DeepSeek and Copilot work? =

DeepSeek and Copilot work the same way as Gemini:
- Clicking the button automatically copies the prompt to clipboard
- A notification appears confirming the copy
- The platform opens in a new tab
- User can paste the prompt with Ctrl+V and get the summary

This approach is used because these platforms don't support URL parameters for prompts. Meta AI and Qwen used to work this way. Now they accept the prompt in the address, like Claude or ChatGPT, so their buttons open with the prompt already written. The list is checked on every release, because platforms add and remove this support over time.

= What is the floating bar and how is it different from the inline buttons? =

The inline block is inserted before or after the post content and scrolls away with it. The floating bar stays pinned to the viewport while the reader scrolls: on the left edge, the right edge or along the bottom, always as icons. It shows the same buttons you enabled, in the same order.

You can use both at once, or only the bar: untick every destination under "Where the buttons appear" and turn the bar on with its own destinations. Three options control how intrusive it is: showing it only while the reader scrolls down, hiding it on phones (the default), and standing aside on pages where the inline block is already showing. On phones the side positions turn into a bottom strip, because a vertical column would eat the reading area.

Its CSS and JavaScript live in separate files that are only loaded on pages where the bar is shown, so a site with the bar off downloads nothing extra.

= How does compact mode on phones work? =

It reduces how much room the buttons take on a phone screen, and only there: the desktop layout is untouched. There are two shapes. "A single Share button" hides the whole block behind one button that opens it. "A few buttons plus a More button" keeps the first two to five visible (you choose how many) and puts the rest behind More. The visible ones are the first in the order you set by dragging the platforms in the settings, so put the ones that matter at the top of their group.

Everything is rendered by the server and only shown or hidden with CSS, so it works with page caching and the buttons are in the page for search engines either way.

= What do the Copy link and Share buttons do? =

"Copy link" copies the post address to the clipboard and confirms it on screen. "Share" hands the post to your device own share sheet, which is where WhatsApp, Signal, Instagram, Messages or AirDrop appear — anything installed on the device, without a button per app.

The Share button is only rendered where the browser supports it (most mobile browsers, Safari and Chrome on desktop), so visitors on a browser without it never see a button that does nothing. Both count in the analytics like any other button.

= What does the Google Preferred Source button do? =

It opens the Google tool where a reader marks your site as a preferred source, so your stories show up more for them in Top Stories.

* It is a plain link: nothing loads from Google until someone clicks it.
* Analytics counts its clicks and, in the platforms table, lists the posts they came from. Google does not show you this anywhere.
* It is off until you tick it, under Google in the button list.
* It is not shown if your site lives in a subfolder or asks search engines not to index it, because Google cannot list those. The settings screen tells you when that happens.

= Can I show the Google Preferred Source button apart from the others? =

Yes. Under the button list, choose where it goes:

* **With the social networks**, like any other button.
* **In a section of its own**, at the end of the block, with a title of your choice or a thin line above it.
* **Outside the block**: it leaves the block and the floating bar, and shows only where you place it.

To place it anywhere (a Shortcode block, a widget, a template), use `[ayudawp_share_buttons buttons="google_preferred" title_text="Follow us on Google"]`. A shortcode that names it shows it whatever you chose above.

= What are the UTM options for? =

With UTM tagging on, the address that travels to each platform carries utm_source with the platform name, utm_medium with social, ai or share, and the campaign name if you filled one in. That lets your analytics tool separate visits coming back from a share button from the rest of the referral traffic. It is off by default, because many analytics setups already group referrals the way their owners want.

= Does it work with page builders? =

Yes, it's compatible with:
- Gutenberg (WordPress block editor)
- Elementor, Beaver Builder, Divi
- Any page builder that respects WordPress hooks
- Manual insertion via shortcode works everywhere

= Can I disable automatic insertion completely? =

Yes, set automatic insertion to "Disabled" in settings and use only the shortcode `[ayudawp_share_buttons]` where you want buttons to appear.

= What happens to my settings when I uninstall the plugin? =

By default, your settings are preserved even after uninstalling. However, you can enable the "Delete all plugin data when plugin is deleted" option in the Data cleanup section if you want automatic cleanup. This will also delete all analytics data stored in the database.

= Which SEO plugins are compatible? =

The plugin detects and integrates with:
- Yoast SEO
- Rank Math
- All in One SEO
- SEOPress
- The SEO Framework
- Visibility

When enabled, buttons will automatically be hidden on content marked as noindex in any of these plugins. With Visibility, both the per-post noindex and its bulk per-content-type rules (with their per-post exceptions) are respected.

== External services ==

This plugin connects to third-party AI providers **only when the inline AI Summary feature is enabled** and an AI Connector is configured (or the WP AI Client is available). All other features — social and AI share buttons, click analytics, the extractive PHP fallback summary — run entirely on your own server and do not contact any external service.

= AI Summary generation =

When a post is saved (or when the editor / a visitor explicitly clicks "Regenerate" or "Generate AI summary"), the plugin uses the WordPress 7.0 AI Client (`wp_ai_client_prompt()`) to request a short summary from whichever provider you configured in **Settings > Connectors**. The plugin never stores your API keys — they are managed centrally by WordPress core.

What is sent:

- Post title (plain text)
- Post content (HTML stripped, up to ~3000 characters of plain text)
- A short instruction asking the provider to return a summary

What is **not** sent: API keys (managed by core Connectors), visitor IP addresses, user accounts, analytics data, or any other personal data of your readers.

Possible destinations (depending on which Connector your administrator activates):

- **OpenAI** — [Terms of use](https://openai.com/policies/terms-of-use) · [Privacy policy](https://openai.com/policies/privacy-policy)
- **Anthropic** — [Terms of service](https://www.anthropic.com/legal/consumer-terms) · [Privacy policy](https://www.anthropic.com/legal/privacy)
- **Google AI** — [Terms of service](https://policies.google.com/terms) · [Privacy policy](https://policies.google.com/privacy)

If you do not configure any Connector, or your WordPress version is below 7.0, the plugin falls back to the local PHP extractive summarizer and no external request is made.

= How to opt out =

- Set **AI Summary generation** to *Disabled* in the **AI Summary** tab of the plugin settings.
- Or choose *Extractive only* to keep summaries fully local — zero external requests, ever.
- Or choose *AI only* while leaving no Connector configured — no requests will be sent, and summaries simply won't be generated.
- The visitor-facing "Generate AI summary" button is off by default; if enabled, you can additionally restrict it to the extractive PHP path so visitor clicks never reach an external provider.

= Sharing buttons =

The social and AI sharing buttons render as `<a>` / `<button>` elements that open the respective destination only when the **visitor** clicks them — your server does not contact those services. The same goes for the Google Preferred Source button, a plain link to [Google's preferred sources tool](https://www.google.com/preferences/source) with your domain in it: no Google script is loaded ([Terms of service](https://policies.google.com/terms) · [Privacy policy](https://policies.google.com/privacy)). The plugin's analytics endpoint that records button clicks runs locally on your own site.

== Screenshots ==

1. Inline AI summary collapsible block above the share and AI buttons on a post
2. Mobile responsive view
3. Plugin settings for social and AI sharing buttons
4. Prompt opened on ChatGPT
5. Editor controls box: hide buttons or manage the AI summary
6. SEO integration setting with detected SEO plugin
7. Analytics dashboard with timeline chart and platform breakdown
8. Period comparison with timeline overlay and stat card indicators
9. Dashboard widget with sparkline and top platforms
10. AI Summary settings with WP 7.0 AI Client status and Connectors link
11. Visitor-facing "Generate AI summary" button for posts without a stored summary

== Changelog ==

= 2.7.1 =
Fixes AI summaries that now and then came out in English on posts written in another language: the language is now named in the request itself. Analytics uses the full width, with the banner at the bottom.

* Improved: Analytics now uses the full width of the screen, with the AyudaWP services banner as a strip at the bottom instead of a side column, as in VigIA. The charts and the two tables have room again, and on a laptop screen the tables sit side by side. The Share Buttons and AI Summary tabs keep the banner in the side column.
* Fix: Now and then an AI summary came out in English for a post written in another language. The only language rule was a single line in English asking the AI to match the post, in a request that is otherwise in English too. The language is now named in the instructions and again in the request: the language of the post when Polylang or WPML is active, and otherwise the language the post is written in, with the site language named as the usual one unless it is English (many sites that write in another language never change the default English setting). Quotes, names and technical terms in another language no longer count. Existing summaries are not regenerated: use Regenerate on any that came out in the wrong language. Developers can set the language per post with the `ayudawp_aiss_summary_locale` filter.

= 2.7.0 =
New space for your own content inside the summary box (an ad, a form, any script), loaded only when a reader opens it. Qwen now opens with the prompt already written, the editor warns when a post is too short for a summary, and button labels show as written.

* New: Custom content inside the summary box, below the summary. Anything fits: text, HTML or scripts, such as an ad (AdSense, Google Ad Manager or any other), a newsletter form, a map or a notice. It can also be left empty for an ad server or Google Tag Manager to fill the space, the element with id `ayudawp-aiss-custom`. It only loads the first time a reader opens the box, so nothing is requested into a folded box, and opening it fires the `ayudawp_aiss_custom_visible` event, also pushed to the dataLayer, so a tag can wait for it. Scripts that queue their work, like AdSense, Google Ad Manager or Tag Manager, work this way; scripts that rely on document.write do not. It shows only on the post's own page, never in listings or feeds, and stays out of Google snippets with the rest of the box. Optional label above it, Advertisement unless you write your own, and optional minimum and maximum heights. Only users who can publish unfiltered HTML can change the content: on a network, the super admin, not the administrator of each site, who sees it read-only. Developers can remove or change it per post with the `ayudawp_aiss_summary_custom_html` filter.
* Improved: Qwen now opens with the prompt already written, instead of copying it to the clipboard for you to paste. Checked platform by platform in October 2026: Gemini, DeepSeek and Copilot still offer no way to pre-fill the prompt from the address, so those three keep copying it.
* Improved: The block editor panel and the classic box now say when a post is too short for an automatic summary. Since 2.6.0 such a post shows no automatic summary on the site, but the editor kept showing the stored one, and you only found out when Regenerate failed. The notice also says that a summary you write by hand is shown.
* Fix: Button labels showed every word capitalized, such as Copiar Enlace or Fuente Preferida De Google, on the site and in the settings preview. They now show as written.
* Fix: On a laptop screen, Analytics put its two tables side by side in too little room, so post titles broke into several lines and column headers were cut off. They now sit side by side only when the content column has room for both, and the click columns take just the width they need.

For older changelog entries, please check the [changelog.txt](https://plugins.svn.wordpress.org/ai-share-summarize/trunk/changelog.txt) file

== Upgrade Notice ==

= 2.7.1 =
Fixes AI summaries that now and then came out in English on posts written in another language: the language is now named in the request itself. Analytics uses the full width, with the banner at the bottom.

== Advanced Usage ==

= Shortcode parameters =

The `[ayudawp_share_buttons]` shortcode accepts several parameters:

**buttons**: Comma-separated list of buttons to display
- Example: `[ayudawp_share_buttons buttons="claude,chatgpt,deepseek,twitter,mastodon"]`
- Available: twitter, linkedin, facebook, telegram, whatsapp, email, raindrop, reddit, bluesky, line, mastodon, threads, pinterest, copy_link, web_share, google_preferred, claude, chatgpt, google_ai, gemini, grok, perplexity, deepseek, mistral, copilot, qwen, meta_ai

**style**: Visual style to use
- Example: `[ayudawp_share_buttons style="outline"]`
- Options: minimal, brand, outline, dark, custom, icons-only

**size**: Button size preset
- Example: `[ayudawp_share_buttons size="compact"]`
- Options: compact, normal, large, fluid

**show_icons**: Show icons with text (for non-icons-only styles)
- Example: `[ayudawp_share_buttons show_icons="true" style="brand"]`
- Options: true, false

**icon_style**: Icon corner style (for icons-only mode)
- Example: `[ayudawp_share_buttons style="icons-only" icon_style="circular"]`
- Options: circular, square

**alignment**: Button alignment
- Example: `[ayudawp_share_buttons alignment="center"]`
- Options: left, center

**show_title**: Show or hide the section title
- Example: `[ayudawp_share_buttons show_title="false"]`
- Options: true, false

**title_text**: Custom title text
- Example: `[ayudawp_share_buttons title_text="Share this content"]`

**title_style**: Title HTML element
- Example: `[ayudawp_share_buttons title_style="h3"]`
- Options: h3, h4, h5, h6, span

**ai_title**: Section title for AI buttons group
- Example: `[ayudawp_share_buttons ai_title="Summarize with AI"]`

**social_title**: Section title for social buttons group
- Example: `[ayudawp_share_buttons social_title="Share on social media"]`

**google_title**: Title of the Google Preferred Source section, when it has a section of its own
- Example: `[ayudawp_share_buttons google_title="Follow us on Google"]`

**Combined examples:**

`[ayudawp_share_buttons buttons="claude,deepseek,twitter" style="brand" show_icons="true"]`

`[ayudawp_share_buttons style="icons-only" icon_style="circular"]`

`[ayudawp_share_buttons show_title="true" title_text="Share or Summarize" title_style="h3"]`

`[ayudawp_share_buttons ai_title="Summarize with AI" social_title="Share" style="outline" show_icons="true"]`

`[ayudawp_share_buttons buttons="chatgpt,qwen,meta_ai,mastodon,threads" size="compact" style="brand"]`

`[ayudawp_share_buttons style="icons-only" icon_style="square" size="large"]`

`[ayudawp_share_buttons size="fluid" style="outline" show_icons="true"]`

= AI summary shortcode =

The `[ayudawp_aiss_summary]` shortcode renders the AI-generated summary as a standalone collapsible block. Useful when you want to place the summary somewhere other than where the share buttons are auto-inserted, or when you have buttons disabled but still want to surface the summary.

**post_id**: Render the summary of a specific post
- Example: `[ayudawp_aiss_summary post_id="123"]`
- Defaults to the current post in the loop when omitted

The shortcode outputs nothing when the post has no stored summary, so it is safe to drop into templates without conditional wrappers.

= Printing the blocks in theme hooks =

Some themes rewrite the post content after WordPress filters it, for example to place ads, and end up duplicating whatever plugins added to it. For those, the `ayudawp_aiss_content_anchors` filter prints what goes before the content, what goes after it, or both, in an action of the theme instead:

`add_filter( 'ayudawp_aiss_content_anchors', function ( $anchors ) {
	$anchors['before'] = array( 'hook' => 'mytheme_before_content', 'priority' => 20 );
	$anchors['after']  = array( 'hook' => 'mytheme_after_content', 'priority' => 1 );
	return $anchors;
} );`

* The settings still decide whether the buttons and the summary show and where (before, after or both).
* The stylesheet still loads in the head, exclusions and the floating bar keep working, and each block is printed once per page.
* Leave an anchor as `the_content` to keep it inside the content.

= CSS Customization Guide =

The plugin uses CSS custom properties for all brand colors. You can override these in your theme to change any platform color globally:

**Override platform colors:**
`.ayudawp-share-buttons {
    --ayudawp-claude: #ff0000;
    --ayudawp-chatgpt: #00ff00;
}`

**Main container classes:**
- `.ayudawp-share-buttons` - Main wrapper container
- `.ayudawp-buttons-container` - Direct container for all buttons
- `.ayudawp-title` - Title element before buttons
- `.ayudawp-section-title` - Section title with extra top margin
- `.ayudawp-aiss-centered` - Applied when centered alignment is enabled

**Button classes:**
- `.ayudawp-share-btn` - Base class for all buttons (both `<a>` and `<button>` elements)
- `.ayudawp-icon-wrapper` - Container for button icons
- `.ayudawp-button-text` - Text label inside buttons
- `.ayudawp-icon` - SVG icon element

**Style modifier classes:**
- `.brand` - Brand colors style
- `.outline` - Outline style (brand-colored borders)
- `.minimal` - Minimal style
- `.dark` - Dark background style
- `.custom` - Custom colors style
- `.icons-only` - Icons-only mode
- `.with-icons` - Text buttons with icons
- `.circular` - Circular icon buttons
- `.square` - Square icon buttons

**Size modifier classes:**
- `.size-compact` - Compact button size
- `.size-large` - Large button size
- `.size-fluid` - Fluid width buttons

**Platform-specific classes (on buttons):**
- `.twitter`, `.linkedin`, `.facebook`, `.telegram`, `.whatsapp`
- `.email`, `.raindrop`, `.reddit`, `.bluesky`, `.line`
- `.mastodon`, `.threads`, `.pinterest`
- `.claude`, `.chatgpt`, `.google-ai`, `.gemini`, `.grok`
- `.perplexity`, `.deepseek`, `.mistral`, `.copilot`
- `.qwen`, `.meta-ai`
- `.ai` - Applied to all AI platform buttons

**Example: Change Claude button color:**
`.ayudawp-share-buttons {
    --ayudawp-claude: #your-color;
}`

**Example: Change all AI buttons background:**
`.ayudawp-share-buttons .ayudawp-share-btn.ai {
    background: #f0f0f0;
}`

**Example: Hide specific button:**
`.ayudawp-share-btn.facebook {
    display: none;
}`

**Customize or remove separator lines:**
The main container has top and bottom border lines. You can hide either of them from the settings (Share Buttons → Separator lines) with no code. CSS is only needed to replace them with your own style:

`.ayudawp-share-buttons {
    border-top: 2px dashed #ccc;
    border-bottom: 2px dashed #ccc;
}`

**Important notes:**
- The use of `!important` is no longer needed for most overrides
- Use CSS custom properties to change brand colors cleanly
- Test on both desktop and mobile viewports
- Icons-only buttons have fixed dimensions (44px default, 36px compact, 54px large)

== Technical Details ==

= System requirements =
* WordPress 6.1 or higher (7.0+ for AI-generated summaries)
* PHP 7.4 or higher (compatible up to PHP 8.4)
* Theme compatible with wp_head() and wp_footer()

= Performance features =
* Modular file structure for maximum efficiency
* Selective loading of resources (CSS/JS only where buttons display)
* No external dependencies: the front end script is plain JavaScript and does not load jQuery
* The floating bar ships its CSS and JS as separate files, loaded only where the bar is shown
* Optimized CSS with custom properties and minimal specificity
* Lightweight SVG icons (under 1KB each)
* Minimal database impact: analytics queries are cached (5 min) and compatible with persistent object cache (Redis, Memcached)
* Smart responsive layouts
* REST API for lightweight analytics data retrieval

= Developer features =
* Clean, documented code
* WordPress coding standards compliant
* Hook-based architecture
* Modular class structure in /includes folder
* Extensible icon system using Simple Icons
* Translation ready
* Comprehensive shortcode API
* SEO-friendly markup options
* Post meta for individual exclusions
* Centralized platform color definitions
* Filters: `ayudawp_aiss_floating_bar_buttons` (which buttons the bar shows), `ayudawp_aiss_show_floating_bar` (whether it renders on a given request), `ayudawp_aiss_utm_args` (the campaign parameters added to a shared link), `ayudawp_aiss_trusted_proxies` (which addresses may set the forwarding header behind a CDN or load balancer), `ayudawp_aiss_content_anchors` (print the blocks in theme actions), `ayudawp_aiss_summary_min_words` (minimum length for a summary), `ayudawp_aiss_summary_custom_html` (the custom content inside the summary box, per post) and `ayudawp_aiss_google_preferred_domain` (the domain sent to Google)


== Support ==

= Need private support or custom development? =
Do you need one-on-one help, priority troubleshooting, or a custom feature, integration, or tweak built specifically for your site? I offer private support and custom development. Just [contact me](mailto:ai-share-summarize@ayudawp.com) and tell me what you need.

= Need help or have suggestions? =
* [Official website](https://servicios.ayudawp.com/)
* [WordPress support forum](https://wordpress.org/support/plugin/ai-share-summarize/)
* [YouTube channel](https://www.youtube.com/AyudaWordPressES)
* [Documentation and tutorials](https://ayudawp.com/)

**Love the plugin?** Please leave us a 5-star review and help spread the word!

== About AyudaWP ==

We are specialists in WordPress security, SEO, AI and performance optimization plugins. We create tools that solve real problems for WordPress site owners while maintaining the highest coding standards and accessibility requirements.