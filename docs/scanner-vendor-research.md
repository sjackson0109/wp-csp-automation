# Scanner and Crawler Vendor Research

Source material for the Continuous Intelligence &gt; Vendors catalogue
(`sam_scanner_vendors`, `Intelligence\Scanner_Vendor_Store`). Researched
directly against each vendor's own current documentation, 8 September 2026.
Two statuses are kept strictly separate and must never be conflated, the
same convention `docs/dns-provider-test-matrix.md` already established:

- **Confirmed** - a vendor-owned page (developer docs, webmaster/support
  help centre, or a JSON file hosted on the vendor's own domain) was
  fetched directly and states the identification/verification mechanism
  in its own words. The exact page is cited.
- **Not independently verified** - either no vendor-owned page could be
  reached (JS-rendered app shell, geo-block, dead link, repeated
  connection failure), or the only sources found were third-party
  aggregator sites. Reported as such rather than filled in with a
  plausible-sounding guess. Treat these as a starting point for your own
  check, not as ready to trust.

Every row's User-Agent substring, IP range URL, and reverse-DNS suffix is
quoted from what was actually read on the cited page - none is
reconstructed from memory or a third-party list.

## Built into the catalogue

These 16 vendors ship as built-in (`is_builtin = 1`) rows, seeded by
`Activator::seed_default_scanner_vendors()` (schema v27 for the first 6,
v41 for the other 10 - see that method's own docblock for full sourcing).
View or edit them on Continuous Intelligence &gt; Vendors; built-in rows can
be edited (e.g. to add a CIDR range once you have one) but not deleted.

| Vendor | UA pattern | Method | Detail | Source |
|---|---|---|---|---|
| Googlebot | `Googlebot` | fcrdns | `googlebot.com`, `google.com` | developers.google.com/search/docs/crawling-indexing/verifying-googlebot |
| Bingbot | `bingbot` | fcrdns | `search.msn.com` | bing.com/webmasters/help/how-to-verify-bingbot-3905dc26 |
| CCBot (Common Crawl) | `CCBot` | fcrdns | `crawl.commoncrawl.org` | commoncrawl.org/ccbot |
| GPTBot (OpenAI) | `GPTBot` | cidr | openai.com/gptbot.json | developers.openai.com/api/docs/bots |
| ClaudeBot (Anthropic) | `ClaudeBot` | cidr | claude.com/crawling/bots.json | support.claude.com, article 8896518 |
| PerplexityBot | `PerplexityBot` | cidr | perplexity.com/perplexitybot.json | docs.perplexity.ai/guides/bots |
| YandexBot | `YandexBot` | fcrdns | `yandex.ru`, `yandex.net`, `yandex.com` | yandex.com/support/webmaster/en/robot-workings/check-yandex-robots |
| Baiduspider | `Baiduspider` | fcrdns | `baidu.com`, `baidu.jp` | ziyuan.baidu.com/college/articleinfo?id=1193 (Chinese-language) |
| DuckDuckBot | `DuckDuckBot` | cidr | duckduckgo.com/duckduckbot.json | duckduckgo.com/duckduckgo-help-pages/results/duckduckbot |
| Applebot | `Applebot` | fcrdns | `applebot.apple.com` | support.apple.com/en-us/119829 |
| Sogou web spider | `Sogou web spider` | fcrdns | `sogou.com` | zhanzhang.sogou.com/index.php/help/spider (Chinese-language) |
| SeznamBot | `SeznamBot` | fcrdns | `seznam.cz` | o-seznam.cz/napoveda/vyhledavani/en/seznambot-crawler/ |
| OAI-SearchBot (OpenAI) | `OAI-SearchBot` | cidr | openai.com/searchbot.json | developers.openai.com/api/docs/bots |
| Amazonbot | `Amazonbot` | cidr | developer.amazon.com/amazonbot/ip-addresses/ | developer.amazon.com/amazonbot |
| DuckAssistBot | `DuckAssistBot` | cidr | duckduckgo.com/duckassistbot.json | duckduckgo.com/duckduckgo-help-pages/results/duckassistbot |
| Meta-ExternalAgent | `meta-externalagent` | none | recognition-only - Meta documents no IP/DNS mechanism | developers.facebook.com/docs/sharing/webmasters/web-crawlers |

Notes carried over from research but deliberately **not** built in as a
separate row:

- **Google-Extended** and **Applebot-Extended** are robots.txt-only
  training-use control tokens, not separate crawlers with their own HTTP
  identity - they're covered by the Googlebot and Applebot rows above.
- **Cohere** currently runs no active crawler at all, per its own current
  docs (`docs.cohere.com/docs/cohere-web-crawlers`) - nothing to seed.

## Researched, not independently verified (search &amp; AI crawlers)

Vendor-owned pages could not be confirmed for these. Do not add as a
"verified" catalogue entry without re-checking yourself first.

| Vendor | Crawler | UA substring (reported) | Why unverified |
|---|---|---|---|
| Naver | Yeti | `Yeti` | help.naver.com / searchadvisor.naver.com unreachable to automated fetch; rDNS suffix `naver.com` only corroborated secondhand |
| Mail.ru | Mail.RU_Bot | `Mail.RU_Bot` | help.mail.ru unreachable (repeated connection failures); commonly-cited `95.163.248.0/21` range appears only on third-party sites |
| Huawei (Aspiegel) | PetalBot | `PetalBot` | vendor's own page (aspiegel.com/petalbot) is internally inconsistent - instructs checking for `aspiegel.com` in the hostname, but its own worked example uses `petalsearch.com` |
| ByteDance | Bytespider | `Bytespider` | official page (zhanzhang.toutiao.com) is JS-rendered/unreachable; secondary sources conflict on whether any verification method exists at all |
| Diffbot | Diffbot | `Diffbot` | diffbot.com/docs/crawl/faq/robots-txt confirms UA and robots.txt compliance only; no IP/DNS method published |
| Hive | ImagesiftBot | `ImagesiftBot` | imagesift.com/about confirms UA only; no IP/DNS method published |
| Timpi | Timpibot | `Timpibot` | no crawler/bot documentation page exists on any Timpi-owned domain |
| Webz.io | omgili (omgilibot) | `omgili` | webz.io's blog names the UA and points to omgili.com/Crawler.html, which has an expired TLS certificate |

## Commercial and research security scanners

Deliberately **not** seeded as built-in catalogue rows, by design - see
`Scanner_Vendor_Store`'s own class docblock: published ranges for these
change over time, several vendors document no distinctive User-Agent at
all (they default to spoofing an ordinary browser), and asserting a
guessed or stale range in a security product is worse than asserting
nothing. Add any of these yourself via the "Add a vendor" form once you
have a source you trust - the source URL is required on that form for
exactly this reason.

| Vendor | Product | UA substring | Verification | Detail | Source |
|---|---|---|---|---|---|
| Qualys | Cloud Platform / PCI ASV scanner | none - spoofs an old iPhone Safari UA | Published CIDR list | `64.39.96.0/20`, `139.87.112.0/23`, `69.67.179.0/24`, `69.67.181.0/24` | docs.qualys.com/en/pci/merchant/getting_started/check_scanner_ip_addresses.htm |
| Tenable | Vulnerability Management / WAS | none - spoofs local browser UA | Published CIDR + JSON | Regional tables + `/ip-ranges/data.json` | docs.tenable.com/vulnerability-management/Content/Settings/Sensors/CloudSensors.htm |
| Rapid7 | InsightVM / Nexpose | none - spoofs IE7 UA | No fixed range | Scans run from customer-deployed or 1:1-disclosed engine IPs, not a published list | docs.rapid7.com/insightvm/pairing-a-hosted-scan-engine/ |
| Invicti / Netsparker | Enterprise/Standard | not documented | Published per-region IP list | e.g. US: `54.88.149.100`, `52.0.216.190`, `38.123.140.0/24` | docs.invicti.com/ie-is/trustlist-us |
| Acunetix (Invicti-owned) | Online / 360 | not documented | Hostname allowlist, not raw CIDR | `scanners-us.invicti.com` etc. | acunetix.com/support/docs/whitelisting-for-acunetix-online-us/ |
| Detectify | Surface Monitoring / App Scanning | `Detectify` / `Mozilla/5.0 (compatible; Detectify)` | Published IP list | EU/US/India IPs listed | docs.detectify.com/network-setup/scanner-ip-addresses |
| PortSwigger | Burp Suite Scanner | none - embedded Chromium, standard Chrome UA | No fixed range | Runs from customer/tester infrastructure | portswigger.net/burp/documentation/scanner/browser-powered-scanning |
| Intruder.io | Intruder scanner | not documented | Published CIDR list | Base `64.52.19.0/24` + regional ranges | help.intruder.io/en/articles/1635683-what-ips-do-i-need-to-add-to-my-allowlist |
| Probely (Snyk API &amp; Web) | Probely scanner | `https://probely.com/sos` (embedded token) | Published IP list (region-varying) + documented UA token | e.g. US `18.235.241.170` | help.probely.com (outgoing IP + UA-header articles) |
| WPScan | CLI vulnerability scanner | not reconfirmed on a current vendor page | No fixed range | Self-hosted open-source CLI; no vendor-owned scanning IP block | github.com/wpscanteam/wpscan/wiki/WPScan-User-Documentation |
| Sucuri | SiteCheck (free scanner) | none - rotates UA/referrer by design | None found for SiteCheck specifically | Published IPs found (`192.88.134.0/23` etc.) belong to the separate WAF/CloudProxy product, not SiteCheck | blog.sucuri.net/2012/10/ask-sucuri-how-does-sitecheck-work.html |

## Monitoring, SEO, and internet-research bots

Also deliberately left out of the built-in catalogue - several have no
fixed IP range or use a non-standard verification scheme this plugin's two
supported methods (CIDR list, reverse-DNS suffix) don't cleanly cover.
Useful reference if you want to authorise one of these for your own site.

| Vendor | Crawler | UA substring | Verification | Detail | Source |
|---|---|---|---|---|---|
| UptimeRobot | monitor | `UptimeRobot` | Published IP list | cdn.uptimerobot.com/api/IPv4andIPv6.txt (+JSON, +DNS) | uptimerobot.com/help/locations/ |
| Pingdom (SolarWinds) | probe servers | not reconfirmed on vendor page | Published IP list | my.pingdom.com/probes/ipv4 (+ipv6, +RSS) | documentation.solarwinds.com .../pingdom-probe-servers-ip-addresses |
| StatusCake | monitoring nodes | not reconfirmed on vendor page | Published IP list (JSON/XML/Text) | app.statuscake.com/Workfloor/Locations.php | statuscake.com/kb/knowledge-base/what-are-your-ips/ |
| Censys | CensysInspect | `CensysInspect` | Published CIDR + ASN list | e.g. `66.132.159.0/24`; `AS398722`, `AS398705` | docs.censys.com/docs/opt-out-of-data-collection |
| Shodan | scanners/census nodes | none found | None found | No IP list or rDNS suffix published anywhere on help.shodan.io | help.shodan.io/the-basics/on-demand-scanning |
| SecurityTrails | scan infrastructure | n/a - TCP port scanner, not HTTP | Published scan-source hostnames+IPs | `1.scan.securitytrails.com`, `2.scan.securitytrails.com` | securitytrails.com/support/knowledge-base/faq |
| Internet Archive | archive.org_bot | not reconfirmed on vendor page | None found | archive.org/robots.txt has no bot-specific rule; IA's own GitHub org has an open, unresolved discussion asking for a verification method | archive.org/robots.txt; github.com/internetarchive/heritrix3/discussions/507 |
| Ahrefs | AhrefsBot | `AhrefsBot` | Published IP list + rDNS suffix | api.ahrefs.com/v3/public/crawler-ip-ranges; suffix `ahrefs.com`/`ahrefs.net` | ahrefs.com/robot; help.ahrefs.com/en/articles/78658 |
| Semrush | SemrushBot family | `SemrushBot` | None found | Vendor explicitly states "do not try to block via IP as we do not use any consecutive IP blocks" | semrush.com/bot/ |
| Majestic | MJ12bot | `MJ12bot` | Neither IP list nor rDNS | Per-site `CRAWLER-IDENT` arranged by emailing the vendor | mj12bot.com/ |
| Moz | DotBot / Rogerbot | not reconfirmed | None found | Every moz.com help URL for these crawlers now 404s or blocks automated fetch | (no reachable vendor page) |

## Adding one of these to your site

Continuous Intelligence &gt; Vendors &gt; "Add a vendor". A source URL is
required on that form so the record stays traceable to where the
verification method came from - use the Source column above. If a vendor
has no fixed IP range or DNS suffix (several above), set Verification
method to "None": the entry still lets the Identities tab recognise and
label the traffic by its User-Agent, correctly shown as unverified
(`claimed_crawler_unverified`) rather than a fabricated "verified" state.
