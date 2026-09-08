# DNS Provider Setup Guides

Resolves roadmap issue #291, as a product-usability workstream after the
WordPress.org resubmission (PR A/B/C) was complete. This is user-facing
setup guidance for the credential fields each of the 41 built-in DNS-01
provider drivers already asks for on the Certificates page -- it doesn't
add or change any field; see each driver's own `fields()` method in
`includes/certificates/providers/class-provider-*.php` for the
authoritative, current field list if this document and the code ever
disagree (the code always wins).

**Research conducted:** 2026-09-08, against each provider's own current
documentation at that time (see "Future maintenance" below).

## How to read this document

Every entry follows the same structure: where to create the credential,
the narrowest permission/scope that works, whether it can be scoped to a
single zone, a field-by-field mapping from the provider's own naming to
this plugin's form, how to rotate or revoke it, common errors, and the
Terms of Service / Privacy Policy links already disclosed in `readme.txt`'s
"External services" section.

**Evidence/verification status matters here.** Following the same
convention `docs/dns-provider-test-matrix.md` already established for test
coverage -- never conflating "verified" with "assumed" -- every entry ends
with an evidence line stating plainly what was confirmed by fetching the
provider's own current documentation versus corroborated only through
search results versus **not independently verified**. Where a specific
detail is marked not verified, confirm it against the provider's own
current documentation before relying on it -- provider UIs and permission
models change over time and this document can drift the same way any
third-party integration guide can.

**Two providers in this list are not third-party vendors at all.**
PowerDNS and RFC 2136 (TSIG dynamic DNS updates) are self-hosted DNS
server software/protocols the administrator already operates themselves --
there is no vendor account, credential-creation page, or Terms of Service
to disclose, and their entries say so explicitly rather than inventing one.
acme-dns is usually self-hosted too, though a public shared instance
exists; see its own entry for the distinction.

## Future work, explicitly out of scope for this document

Automated credential and zone-access verification (testing that a
configured credential can actually reach the right zone before an
administrator attempts a real issuance) is listed in issue #291 itself as
future work, not part of this documentation pass.

## cPanel deployment

Already documented in full in `docs/certificates.md`'s "Installing the
certificate: platform-dependent" section (see its "cPanel (including most
LiteSpeed shared hosting) -- automatic" subsection) -- this document
doesn't duplicate those steps. In summary: create a token under cPanel's
**Security → Manage API Tokens**, scoped to SSL feature access only, and
enter the host, cPanel username, and token on the Certificates page.

- **Credential rotation/revocation**: cPanel's **Security → Manage API
  Tokens** page lists every existing token with a revoke action next to
  it. Revoking a token immediately invalidates it; there is no separate
  "rotate" action, so rotation means creating a new token, updating it on
  the plugin's Certificates page, and then revoking the old one.
- **Common errors**: an authorization error on deploy is a host policy
  issue, not a plugin one, per `docs/certificates.md`'s own caveat -- some
  resellers disable API tokens or the SSL UAPI module entirely. A host
  running AutoSSL may already be renewing certificates independently;
  check before enabling this plugin's cPanel deployment alongside it, to
  avoid both mechanisms racing to install a certificate for the same
  domain.

## Provider guides

## acme-dns

- **Credential-creation link**: There is no vendor "credential creation" web page — acme-dns is a self-hosted, open-source DNS server (https://github.com/acme-dns/acme-dns) that you run yourself, OR you point at the community-run public instance at `https://auth.acme-dns.io`. Either way, the credential is created by sending an HTTP `POST` to the server's `/register` endpoint (e.g. `curl -X POST https://auth.acme-dns.io/register`), not through a web UI.
- **Minimum required permission/scope**: There is no permission/role system to narrow — the `/register` call itself creates a brand-new, single-purpose account scoped only to one `subdomain`/CNAME target, which is already the minimum possible privilege (it cannot see or touch any other subdomain on the server). The only optional narrowing available is the `allowfrom` parameter in the `/register` request body (a list of CIDR ranges), which restricts which client IPs may later call `/update` with these credentials.
- **Zone/project scoping guidance**: Each `/register` call is inherently scoped to exactly one generated `subdomain` under the acme-dns server's own domain — it is never account-wide in the way a cloud DNS API key can be. The tradeoff is architectural, not permission-based: you still delegate the `_acme-challenge` CNAME for your real domain to that acme-dns subdomain, so whoever controls the acme-dns server (yourself if self-hosted; the `joohoi`/community operator if using the public instance) can issue certificates for any domain that points a CNAME at it. The acme-dns project's own documentation states plainly: "you are effectively authorizing the acme-dns instance to act on your behalf" for ACME challenges, and explicitly recommends self-hosting rather than relying on the public instance for anything beyond testing.
- **Field-by-field mapping**:
  - `server_url`: the base URL of the acme-dns instance you are using — either your own self-hosted instance's URL, or `https://auth.acme-dns.io` for the public instance (this is also the plugin's placeholder value).
  - `username`, `password`, `subdomain`: all three come verbatim from the JSON response of your one-time `POST {server_url}/register` call, which returns `{"username": "...", "password": "...", "subdomain": "...", "fulldomain": "...", "allowfrom": []}`. There is no dashboard to look these up later — they are shown only once, at registration time, so they must be captured and stored immediately (e.g. save the raw JSON response before entering values into the plugin).
- **Credential rotation/revocation guidance**: acme-dns has no documented rotation/reset/delete API or UI for an existing registration — the GitHub README does not describe an "update password" or "delete account" endpoint. To rotate, the practical approach is to run `/register` again to mint a brand-new username/password/subdomain triple, update the plugin's stored credentials and the corresponding `_acme-challenge` CNAME record to point at the new `fulldomain`, and treat the old credential set as abandoned (self-hosted operators can also manually delete the row from the server's own SQLite/Postgres database, but that is a server-admin action outside any documented API — **not independently verified — confirm before publishing** if you plan to document that path).
- **Common errors and resolutions**:
  - CNAME not yet created/propagated for `_acme-challenge.<yourdomain>` pointing at `<subdomain>.<acme-dns-host>` — DNS-01 validation will fail with an NXDOMAIN/lookup error from Let's Encrypt until the CNAME is live and propagated.
  - Wrong `server_url` (missing scheme, trailing slash, or pointing at a self-hosted instance's internal/private address that Let's Encrypt's own resolvers can reach but your plugin's update call also needs to reach) — causes connection failures on the `/update` call.
  - Using credentials from a different `/register` call than the CNAME target — `username`/`password`/`subdomain` must all come from the same registration response; mixing values from separate registrations causes 401/403 errors on `/update`.
  - Self-hosted instance not reachable from the public internet (acme-dns must be internet-facing so Let's Encrypt can resolve the delegated `_acme-challenge` CNAME) — firewall/NAT misconfiguration is a common cause of validation timeouts.
- **Terms of Service / Privacy Policy**: Not applicable in the conventional sense — acme-dns is open-source software (MIT-licensed per its GitHub repo), so a self-hosted instance is governed only by whatever policy the administrator running it chooses to set. If using the public `auth.acme-dns.io` instance, no formal Terms of Service or Privacy Policy document was found published by that operator during this research; the operator's `text.txt` disclaimer (https://github.com/joohoi/acme-dns.io/blob/master/text.txt) only states the security implication described above and that the public instance is offered "for free" as a testing convenience. **Not independently verified — confirm before publishing** if a formal ToS/Privacy Policy for the public instance exists elsewhere.
- **Evidence/verification status**: Verified live via GitHub (https://github.com/acme-dns/acme-dns and https://github.com/joohoi/acme-dns.io/blob/master/text.txt) for the `/register` request/response shape, the absence of a rotation/delete API, and the public-instance disclaimer text. No vendor "legal" page exists to verify for the self-hosted case (expected and stated above). The public-instance ToS/Privacy question is explicitly flagged as unverified above rather than assumed absent.

---

## Akamai (Edge DNS)

- **Credential-creation link**: Akamai Control Center → Identity and Access Management → API clients (https://techdocs.akamai.com/developer/docs/set-up-authentication-credentials documents the exact flow: create an API client, choose "Quick" for your own credentials or "Advanced" to scope a client for someone else, then Download or Copy the resulting `.edgerc` block).
- **Minimum required permission/scope**: When creating (or editing) the API client, under "APIs" select the API service named **"DNS—Zone Record Management"** and set its access level to **READ-WRITE** (confirmed both in Akamai's own techdocs get-started page for Edge DNS and corroborated by third-party integration guides using the same exact service name). READ-WRITE is required because DNS-01 needs to create and later delete/clean up TXT records; READ-ONLY would not suffice.
- **Zone/project scoping guidance**: Akamai's access model is group-based, not zone-based: DNS zones are organized under Control Center "groups" (nested organizational containers), and an API client's/role's access is granted per-group rather than per-individual-zone. If your organization keeps the target zone(s) in their own dedicated group, you can scope the API client's role to just that group, which limits it to the zones inside it; if all zones share one group with other Akamai products/properties, the credential is effectively broader than DNS alone. **Not independently verified — confirm before publishing**: whether Edge DNS supports restricting a client to an arbitrary subset of zones within a single group (Akamai's own docs describe group-level access control but do not spell out a finer per-zone allow-list for Edge DNS specifically).
- **Field-by-field mapping**:
  - `host`: the API hostname shown in the generated `.edgerc` block, formatted like `akab-xxxxxxxxxxxxxxxx-xxxxxxxxxxxxxxxx.luna.akamaiapis.net` (this plugin's placeholder example matches Akamai's own convention).
  - `client_token`, `client_secret`, `access_token`: the other three values in that same `.edgerc` block, generated together as one set when you create (or download) the API client credentials in Identity and Access Management.
- **Credential rotation/revocation guidance**: In Control Center → Identity and Access Management → API Clients, open the client and use its credential management options to deactivate the old credential set and generate a new one (Akamai's EdgeGrid credential model supports having an active and an inactive credential per client to allow zero-downtime rotation); deleting the API client entirely revokes all its credentials immediately. **Not independently verified — confirm before publishing** the exact button labels for rotate/deactivate, as Akamai's public docs describe the concept but the specific UI wording was not confirmed live in this research pass.
- **Common errors and resolutions**:
  - API client created without the "DNS—Zone Record Management" permission (or left at READ-ONLY) — TXT record creation calls fail with a 403; fix by editing the client's API grants to add DNS with READ-WRITE.
  - API client's group doesn't include the target zone — Edge DNS API calls for that zone fail with an authorization error even though the credentials are otherwise valid; fix by adding the zone's group to the client's authorized groups (or moving the zone into an already-authorized group).
  - Clock skew between the host running the plugin and Akamai's servers — EdgeGrid authentication is timestamp-based, so significant clock drift causes signature/auth failures; fix by ensuring NTP is correctly configured on the server.
  - Copy/paste errors in the `.edgerc`-style values (e.g., missing the `akab-` prefix on `host`, or trailing whitespace on `client_secret`) — causes generic 401 errors; fix by re-copying values directly from Control Center rather than retyping them.
- **Terms of Service / Privacy Policy**: Terms https://www.akamai.com/legal/portal-terms — Privacy https://www.akamai.com/legal/privacy-statement (reused as given, not re-researched).
- **Evidence/verification status**: Verified live against Akamai's own techdocs (https://techdocs.akamai.com/developer/docs/set-up-authentication-credentials and https://techdocs.akamai.com/edge-dns/reference/get-started) for the credential-creation flow, the four `.edgerc` field names, and the exact "DNS—Zone Record Management" / READ-WRITE permission naming (also corroborated by a third-party tutorial using identical wording). The group-based zone-scoping mechanism is corroborated (Akamai's IAM concepts docs describe groups as the access-control unit) but the fine-grained "can one client be limited to a subset of zones within a group" question and the exact rotation UI labels were not independently confirmed live — both flagged inline above.

---

## Alibaba Cloud DNS (alidns)

- **Credential-creation link**: RAM console → Identity Management → Users → (select or create a RAM user) → "AccessKey" tab → "Create AccessKey" (documented at https://www.alibabacloud.com/help/en/ram/user-guide/create-an-accesskey-pair). Alibaba explicitly recommends creating AccessKeys for a dedicated RAM user rather than the root/primary account.
- **Minimum required permission/scope**: Alibaba Cloud publishes the system policy **AliyunDNSFullAccess** (action `alidns:*` on `Resource: *`) as the standard policy for granting DNS management — this is what Alibaba's own docs point to for DNS automation, and it is account-wide (all domains in the account), not scoped to one domain. Alibaba's DNS RAM-authorization reference states the DNS RAM code (`alidns`) supports "RESOURCE" authorization granularity in principle, meaning a custom policy could in theory target specific resources via ARNs, but the same official documentation set does not publish a narrower pre-built system policy (e.g., a "records-only" or "read+write, no zone deletion" policy) — **not independently verified — confirm before publishing** whether a documented alidns condition/resource-key set exists for writing a custom least-privilege policy; official docs describe the mechanism abstractly but a concrete, confirmed domain-scoped custom-policy example was not found.
- **Zone/project scoping guidance**: Using the system policy AliyunDNSFullAccess grants access to every domain/zone under the Alibaba Cloud account — there is no documented, off-the-shelf way to scope it to one domain only. A custom RAM policy might narrow this via Resource ARNs per Alibaba's general RAM policy-authoring model, but no verified worked example specific to `alidns` domain-level scoping was located; the tradeoff to communicate to admins is "the standard/documented path is account-wide DNS access," and record-level scoping should be treated as unverified/DIY rather than assumed to work as documented.
- **Field-by-field mapping**:
  - `access_key_id` (AccessKey ID) and `access_key_secret` (AccessKey Secret): both are generated together, once, on the "Create AccessKey" screen in the RAM console for the RAM user you've granted AliyunDNSFullAccess (or your custom policy) to. The secret is shown only at creation time and cannot be retrieved again afterward.
- **Credential rotation/revocation guidance**: In the RAM console, under the user's AccessKey tab, you can disable an existing AccessKey, create a replacement one, update the plugin with the new pair, then delete the old (disabled) key — Alibaba's RAM UI supports up to 2 AccessKeys per user specifically to allow this kind of zero-downtime rotation. **Not independently verified — confirm before publishing** the exact button labels (Disable/Delete) as this specific rotation screen was not fetched live in this research pass, only corroborated via the AccessKey creation guide's surrounding documentation.
- **Common errors and resolutions**:
  - AccessKey belongs to a RAM user without AliyunDNSFullAccess (or an equivalent custom policy) attached — API calls fail with a "Forbidden.RAM" / permission-denied error; fix by attaching the policy in RAM → Users → Permissions.
  - AccessKey created for the wrong RAM user, or created under a sub-account whose policy was later revoked — silent 403s; fix by verifying the AccessKey's owning user and current policy attachments in the RAM console.
  - Root/primary-account AccessKey used directly (discouraged) then later deleted/rotated for security reasons — breaks the integration unexpectedly; fix by always using a dedicated RAM user's key, never the primary account's.
  - Region/endpoint confusion — alidns is a global service (not region-scoped like ECS), so pointing at a region-specific endpoint by mistake can cause connection errors; the plugin's driver should be using Alibaba's global DNS endpoint already, but this is worth checking if requests fail with connectivity errors rather than auth errors.
- **Terms of Service / Privacy Policy**: Terms https://www.alibabacloud.com/help/en/legal/latest/alibaba-cloud-international-website-product-terms-of-service — Privacy https://www.alibabacloud.com/help/en/legal/latest/alibaba-cloud-international-website-privacy-policy (reused as given, not re-researched).
- **Evidence/verification status**: Verified live against Alibaba's own docs for the AccessKey-creation path (https://www.alibabacloud.com/help/en/ram/user-guide/create-an-accesskey-pair) and the AliyunDNSFullAccess policy definition (https://www.alibabacloud.com/help/en/ram/developer-reference/aliyundnsfullaccess). The claim that alidns supports resource-level policy scoping is corroborated (official RAM-authorization reference page) but not confirmed with a concrete working example, and the exact AccessKey rotation UI labels were not independently confirmed live — both flagged inline above.

---

## Microsoft Azure DNS

- **Credential-creation link**: Azure Portal → Microsoft Entra ID (Azure AD) → App registrations → New registration (creates the `client_id`/`tenant_id` and the underlying service principal); then within that same app registration, Certificates & secrets → Client secrets → New client secret (creates `client_secret`). Then, separately, on the DNS zone (or its containing resource group) → Access control (IAM) → Add → Add role assignment, to grant the app's service principal the DNS role.
- **Minimum required permission/scope**: The built-in Azure RBAC role **"DNS Zone Contributor"** — confirmed by name in Microsoft's own Learn documentation (https://learn.microsoft.com/en-us/azure/dns/dns-protect-zones-recordsets) as the role that manages Azure DNS public zone resources (`Microsoft.Network/DNSZones`); it does not grant access to unrelated resources like VMs. For an even narrower scope, Microsoft's same article documents a custom-role pattern (e.g. a "DNS CNAME Contributor" example limited to `Microsoft.Network/dnsZones/CNAME/*`) — a similarly scoped custom role limited to TXT records (`Microsoft.Network/dnsZones/TXT/*` plus `Microsoft.Network/dnsZones/read`) could be built for tighter least-privilege than the built-in role, though this plugin's driver has only been verified against the standard DNS Zone Contributor role.
- **Zone/project scoping guidance**: Microsoft's documentation explicitly confirms Azure RBAC role assignments can be scoped at three levels — subscription, resource group, or an individual DNS zone resource — so DNS Zone Contributor can and should be assigned directly on the one DNS zone being used, not the whole subscription or resource group, to avoid granting access to unrelated zones. This is a documented, first-class capability (not a workaround), including an exact CLI form: `az role assignment create --assignee <app-id> --role "DNS Zone Contributor" --scope "/subscriptions/<sub-id>/resourceGroups/<rg>/providers/Microsoft.Network/dnsZones/<zone-name>/"`.
- **Field-by-field mapping**:
  - `tenant_id`: the Directory (tenant) ID shown on the app registration's Overview page in Microsoft Entra ID.
  - `client_id`: the Application (client) ID, also on the app registration's Overview page.
  - `client_secret`: the value generated under Certificates & secrets → Client secrets → New client secret — shown only once at creation time.
  - `subscription_id`: found on the Azure Portal's Subscriptions blade (or on the DNS zone's own Overview page, which lists its parent subscription).
  - `resource_group`: the name of the resource group that contains the target DNS zone, visible on the DNS zone's Overview page.
- **Credential rotation/revocation guidance**: In the app registration → Certificates & secrets, add a new client secret before the old one expires (Azure client secrets always have an expiration date chosen at creation, e.g. 6/12/24 months), update the plugin with the new secret, then delete the old secret entry once confirmed working. To fully revoke access, either delete the client secret(s), delete the app registration itself, or remove the DNS Zone Contributor role assignment from Access control (IAM) on the zone.
- **Common errors and resolutions**:
  - Client secret expired — Azure secrets are time-limited by design (never indefinite), so a previously-working integration will start failing with an authentication error (AADSTS7000222) once the secret's expiry date passes; fix by generating a new secret and updating the plugin.
  - Role assigned at the wrong scope (e.g., on a different resource group than the one containing the zone, or on the zone but the plugin references a different subscription) — causes 403 Authorization errors from the Azure DNS API even though the app registration itself is valid; fix by re-checking the exact scope path used in the role assignment against `subscription_id`/`resource_group`/zone name.
  - Using "DNS Zone Contributor" on a Private DNS zone instead of a public one, or vice versa — Microsoft's own docs note DNS Zone Contributor only covers public zones (`Microsoft.Network/DNSZones`); private zones need the separate "Private DNS Zone Contributor" role — fix by confirming which zone type is in use and assigning the matching role.
  - Propagation delay after a fresh role assignment — Azure RBAC changes can take several minutes to take effect; a validation attempt immediately after assigning the role may fail transiently and succeed on retry.
- **Terms of Service / Privacy Policy**: https://azure.microsoft.com/en-us/support/legal/ (the applicable agreement depends on purchase channel — e.g. Enterprise Agreement, CSP, or pay-as-you-go via the Microsoft Customer Agreement — so administrators should confirm which agreement applies to their specific subscription).
- **Evidence/verification status**: Verified live against Microsoft Learn's own DNS zone-protection article (https://learn.microsoft.com/en-us/azure/dns/dns-protect-zones-recordsets) for the DNS Zone Contributor role name, its exact resource-type scope (`Microsoft.Network/DNSZones`, public zones only), the three-level scoping capability (subscription/resource group/zone) with working CLI syntax, and the custom-role pattern. The app-registration/client-secret creation flow is corroborated across multiple third-party ACME-client integration guides (cert-manager, Certify The Web, certbot-dns-azure) that consistently describe the same Entra ID App registration → Certificates & secrets flow, though no single Microsoft first-party "create an app registration" page was fetched live in this pass to re-confirm current exact menu wording — flagged as corroborated rather than independently re-verified for that specific step.

---

## Bunny.net DNS

- **Credential-creation link**: https://dash.bunny.net/account/settings — Account → Settings → API Keys (confirmed live on bunny.net's own docs, which link directly to that dashboard URL as where the Account API key is found).
- **Minimum required permission/scope**: There is no scoped/least-privilege option — bunny.net's documentation and support material describe exactly **one** API key per account (the "Account API Key"), which grants full access to all Core API actions across the entire account (Pull Zones, Storage Zones, DNS Zones, Stream libraries, billing, etc.), authenticated via the `AccessKey` HTTP header. No DNS-only, read-only, or per-zone key type is documented.
- **Zone/project scoping guidance**: Account-wide access is unavoidable with bunny.net's current API key model — the single Account API Key is not scoped to DNS, let alone to one DNS zone; any credential you give the plugin will also be able to manage CDN pull zones, storage zones, and billing-adjacent settings on the account. Administrators should weigh this account-wide blast radius (e.g., by using a dedicated bunny.net account for DNS-only use, or by tightly restricting who can view the plugin's stored credential) since bunny.net itself provides no narrower alternative today.
- **Field-by-field mapping**:
  - `api_key` (Account API key): copied directly from Account → Settings → API Keys in the bunny.net dashboard (https://dash.bunny.net/account/settings).
- **Credential rotation/revocation guidance**: bunny.net's support material indicates the API key can be reset from the account dashboard, which invalidates the previous key and issues a new one — administrators should update the plugin immediately after resetting, since the old key stops working right away. **Not independently verified — confirm before publishing** the exact button label/flow for resetting, as this specific dashboard screen was not fetched live in this research pass (corroborated only via search-indexed support-article summaries, not a direct live fetch of the reset flow itself).
- **Common errors and resolutions**:
  - Wrong DNS Zone ID supplied to the API alongside a valid key — bunny.net's DNS Zone API requires the numeric DNS Zone ID for record operations; using the wrong ID (or a pull-zone ID by mistake) returns a not-found/permission error even with valid authentication — fix by confirming the DNS Zone ID from the DNS section of the dashboard.
  - Account API key reset/rotated (e.g., by another admin, or during a security response) without updating the plugin — all requests fail with a 401 until the plugin's stored key is refreshed.
  - Because the key is account-wide, a key copied from the wrong bunny.net account (e.g., a personal vs. organizational account) will authenticate successfully but not see the expected DNS zone at all — fix by confirming which account owns the target DNS zone before copying the key.
  - Missing `AccessKey` header formatting — the API expects the raw key as the `AccessKey` header value (not prefixed with "Bearer" or similar); malformed headers return 401s.
- **Terms of Service / Privacy Policy**: Terms https://bunny.net/tos/ — Privacy https://bunny.net/privacy/. Both were re-fetched live during this research pass: the Terms page loaded as bunny.net's current ToS, and the Privacy page loaded as bunny.net's current Privacy & Data Policy, explicitly dated **05/08/2026** — this resolves the prior "not independently re-fetched" caveat noted in earlier research; both links are current as of this pass.
- **Evidence/verification status**: Verified live: API key location (dashboard URL and "API Keys" section wording), the account-wide/single-key model (no DNS-specific or scoped key type found in bunny.net's own docs or support search results), and both legal links (fetched directly, Privacy Policy confirmed dated 05/08/2026). Corroborated but not independently re-fetched: the exact key-reset button/flow in the dashboard (flagged above).

---

## Cloudflare DNS

- **Credential-creation link**: https://dash.cloudflare.com/profile/api-tokens (reached via My Profile → API Tokens → Create Token in the Cloudflare dashboard) — confirmed both on Cloudflare's own Fundamentals docs and corroborated by cert-manager's own Cloudflare DNS-01 setup guide, which independently describes the same path.
- **Minimum required permission/scope**: The exact permission Cloudflare's own UI/docs call it is **`Zone - DNS - Edit`** (shown in the token-permission builder as three dropdowns: resource category "Zone", permission "DNS", access level "Edit") — confirmed verbatim by cert-manager's official documentation, which also notes a second permission is needed alongside it: **`Zone - Zone - Read`** (so the token can resolve the zone ID for the domain before editing its records). Cloudflare also offers a pre-built **"Edit zone DNS"** token template that comes prefilled with the necessary permissions, as an alternative to building a custom token from scratch.
- **Zone/project scoping guidance**: Yes — Cloudflare tokens support scoping "Zone Resources" to a specific zone (e.g., just `example.com`) rather than "All zones from an account," and this is Cloudflare's own recommended practice: granting DNS Edit access scoped to one zone means the token cannot read or modify DNS for any other domain in the account, which is a meaningfully narrower blast radius than the legacy account-wide Global API Key this plugin intentionally avoids.
- **Field-by-field mapping**:
  - `api_token`: the token secret generated at the end of the Create Token flow (My Profile → API Tokens → Create Token → configure `Zone - DNS - Edit` [+ `Zone - Zone - Read`] scoped to the target zone → Continue to summary → Create Token). The secret is shown only once immediately after creation.
- **Credential rotation/revocation guidance**: In My Profile → API Tokens, use the three-dot menu next to the token and select **Roll** to generate a new secret for the same token (invalidating the old secret while keeping identical permissions/scope) — this is Cloudflare's documented "Roll Token" flow. To fully revoke, delete the token instead of rolling it, which permanently disables it with no replacement.
- **Common errors and resolutions**:
  - Token scoped to `Zone - DNS - Edit` but missing `Zone - Zone - Read` — the plugin can fail to resolve the correct zone ID for the domain even though DNS-edit permission is present; fix by adding the Zone Read permission alongside DNS Edit.
  - Token's "Zone Resources" set to a different zone than the one the certificate is being issued for (e.g., scoped to `example.com` but the cert covers `sub.example.org`) — API calls return a 403 for that zone; fix by re-scoping the token to include the correct zone, or to "All zones" if managing multiple domains.
  - Using the legacy Global API Key (paired with account email) instead of a scoped API Token — this plugin's driver expects a Bearer-style API Token, not the legacy key/email pair, so pasting a Global API Key into the `api_token` field will not authenticate correctly.
  - Token rolled or deleted by another administrator (e.g., during a credential audit) without updating the plugin — requests fail with 401/403 until the plugin is updated with the new token value.
- **Terms of Service / Privacy Policy**: Terms https://www.cloudflare.com/terms/ — Privacy https://www.cloudflare.com/privacypolicy/ (reused as given, not re-researched).
- **Evidence/verification status**: Verified live/corroborated across Cloudflare's own Fundamentals docs (token-creation path, "Edit zone DNS" template, zone-resource scoping, Roll Token flow) and cert-manager's official Cloudflare DNS-01 guide (which independently confirms the exact `Zone - DNS - Edit` + `Zone - Zone - Read` permission pair verbatim). The single item not independently re-confirmed live in this pass is the full current permissions-reference table on developers.cloudflare.com (a partial fetch returned only DNS Firewall entries, not the base DNS Edit/Read group, due to page truncation) — the `Zone - DNS - Edit` naming is nonetheless confirmed via the cert-manager cross-reference, so this is treated as verified rather than flagged unverified.

---

## ClouDNS

- **Credential-creation link**: ClouDNS Control Panel → "API & Resellers" (main navigation) → "API Users" section → "Add new user" (documented at https://www.cloudns.net/wiki/article/41/ and https://www.cloudns.net/wiki/article/42/).
- **Minimum required permission/scope**: ClouDNS's model is account-role-based rather than granular-permission-based: a plain **API user** (`auth-id`) has the same access as the main account for API purposes, while a **sub-user** (`sub-auth-id`, prefixed `sub:` per this plugin's field description) can be restricted via ClouDNS's "Delegate zone" feature to specific DNS zones only — making a delegated sub-user the narrower/least-privilege option for a DNS-01 integration versus a full API user.
- **Zone/project scoping guidance**: Yes, narrowing is possible and documented — ClouDNS's sub-user system supports a "Delegate zone" capability that limits a sub-user's access to specific zones rather than the whole account; using a `sub-auth-id` (the "sub:"-prefixed form this plugin's field already anticipates) delegated to only the target zone is the recommended tradeoff versus using the primary account's `auth-id`, which has unrestricted access to every zone on the account.
- **Field-by-field mapping**:
  - `auth_id` (auth-id or sub-auth-id, prefixed "sub:"): for a plain API user, this is the numeric ID ClouDNS auto-generates and displays in the API Users table immediately after you click "Add new user" in API & Resellers; for a sub-user scoped to specific zones, the equivalent value is the sub-account's ID, entered with a `sub:` prefix as this plugin's field expects.
  - `auth_password` (API password): the password you typed into the password field when creating the API user (or sub-user) in the same "Add new user" form — ClouDNS does not generate this for you, you set it directly.
- **Credential rotation/revocation guidance**: In the ClouDNS control panel's API & Resellers → API Users (or Sub-users) list, use the account's password-modification option to change the `auth_password` for an existing `auth-id`/`sub-auth-id` (ClouDNS's API also exposes this as `sub-users/modify-password` for sub-users), or delete the API user/sub-user entirely to revoke access outright (`sub-users/delete` for sub-users). **Not independently verified — confirm before publishing** the exact equivalent delete/modify-password flow for a top-level API user (as opposed to a sub-user) in the control-panel UI — the documented API endpoints found during this research were specific to sub-users.
- **Common errors and resolutions**:
  - Using a `sub-auth-id` delegated to the wrong zone (or not delegated to any zone) — TXT record creation for the target domain fails with an authorization error even though the credentials authenticate successfully; fix by confirming the sub-user's zone delegation includes the exact zone being validated.
  - IP restriction configured on the API user (ClouDNS allows restricting an API user to specific source IPs) that no longer matches the server running the plugin (e.g., after a server migration or IP change) — requests are rejected even with correct credentials; fix by updating or clearing the IP allow-list for that API user.
  - Missing the `sub:` prefix on a sub-user's ID when the plugin's field expects it (this plugin's own field description explicitly calls out the "sub:" prefix requirement) — causes ClouDNS to treat the value as an invalid `auth-id` rather than a `sub-auth-id`, resulting in an authentication error.
  - Password not meeting ClouDNS's minimum length requirement (documented as at least 6 characters for sub-user passwords) — the "Add new user"/"modify password" call itself is rejected at creation/rotation time, before the plugin is ever involved.
- **Terms of Service / Privacy Policy**: Terms https://www.cloudns.net/tos/ — Privacy https://www.cloudns.net/privacy-policy/ (reused as given, not re-researched).
- **Evidence/verification status**: Verified live against ClouDNS's own wiki articles (https://www.cloudns.net/wiki/article/41/, /42/, /115/, /124/, /128/) for the API-user creation path, the auth-id/sub-auth-id/auth-password model, the "sub:" prefix convention, IP restriction support, and the sub-user password-modify/delete endpoints. The single gap is the exact top-level (non-sub-user) API user's own rotate/delete flow in the control-panel UI, which was not located as a distinct documented page during this research and is flagged above rather than assumed identical to the sub-user flow.

---


## deSEC

- **Credential-creation link**: https://desec.io/ — log in, then open the **"Token Management"** tab in the account dashboard, and click **"+"** to create a new token. (The desec.io dashboard is a client-rendered single-page app, so the exact in-app sub-URL for the Token Management tab could not be captured via automated fetch; the tab's existence and workflow are confirmed directly by deSEC's own official docs at https://desec.readthedocs.io/en/latest/auth/tokens.html.)
- **Minimum required permission/scope**: A **Token Policy** scoped to the specific domain and record type `TXT`, with `perm_write: true` for that policy only. Leave the account-level flags `perm_create_domain`, `perm_delete_domain`, and `perm_manage_tokens` all off/false — none of them are needed to create or delete `_acme-challenge` TXT records for DNS-01. deSEC's policy engine matches on a domain/subname/type "longest-prefix match," so this is the most granular scoping available in this entire batch.
- **Zone/project scoping guidance**: Yes — deSEC supports restricting a token to a single domain (and even a single subname/record type) via Token Policies. This is a real advantage over most providers in this batch, which only offer account-wide or whole-domain-list scoping. Tradeoff: configuring per-domain policies is an extra step beyond just generating a token with default (unscoped) settings.
- **Field-by-field mapping**:
  - `api_token` → the token secret string shown **once**, at the moment of creation, in the Token Management tab (or in the API response body for `POST /api/v1/auth/tokens/`). It cannot be retrieved again after creation.
- **Credential rotation/revocation guidance**: Create a new token (with the same domain/TXT policy), update the plugin's stored value, then delete the old token — either via the Token Management tab's delete control in the web UI, or `DELETE https://desec.io/api/v1/auth/tokens/{id}/` via the API.
- **Common errors and resolutions**:
  - Token was created without a domain-scoped policy (i.e., left at default/unscoped settings) → works, but is broader than necessary; recreate with an explicit domain+TXT policy.
  - Token has an `allowed_subnets` (source-IP) restriction that doesn't match the WordPress server's outbound IP → requests fail with 401/403; widen or remove the subnet restriction for that token.
  - Admin only wrote down the token ID, not the secret, since the secret is shown only once → must generate a brand-new token; the old secret cannot be recovered.
  - Policy's "subname" prefix doesn't match where the ACME client writes the challenge (e.g., policy scoped to the apex `@` when the plugin needs to write to `_acme-challenge.<subdomain>`) → writes are rejected; adjust the policy's subname scope.
- **Terms of Service / Privacy Policy**: Terms https://desec.io/terms/ — Privacy https://desec.io/privacy-policy/
- **Evidence/verification status**: Verified live by directly fetching deSEC's own official documentation (desec.readthedocs.io/en/latest/auth/tokens.html), which confirms token creation, the Token Policy/permission model, and deletion via the API. The literal web-dashboard navigation (exact tab clicks) is corroborated by the official docs' own description plus community sources (talk.desec.io), but the rendered dashboard itself could not be fetched directly (JS SPA), so treat the precise on-screen wording as corroborated, not pixel-verified.

---

## DigitalOcean DNS

- **Credential-creation link**: https://cloud.digitalocean.com/account/api/tokens — reached from the control panel via **Account → API → Tokens tab**, then **"Generate New Token."** Verified directly against DigitalOcean's own documentation at https://docs.digitalocean.com/reference/api/create-personal-access-token/.
- **Minimum required permission/scope**: Choose **Custom Scopes** and select `domain:create` and `domain:delete` (DigitalOcean groups all domain-record operations under the general `domain` scope family — there is no separate `domain_record:*` scope). This is narrower than picking the **"Full Access"** (`api:write`) option, which grants every scope on the account, not just DNS.
- **Zone/project scoping guidance**: No — DigitalOcean's `domain:*` scopes apply to **all domains** under the token owner's team; a token cannot be restricted to a single domain/zone. Tradeoff: the narrowest control available is at the scope-family level (`domain:create`/`domain:delete`), not per-domain, so a compromised token can modify DNS for every domain in that DigitalOcean team.
- **Field-by-field mapping**:
  - `api_token` (Personal Access Token, write scope) → the token string shown once on the "Generate New Personal Access Token" screen, format `dop_v1_...`.
- **Credential rotation/revocation guidance**: On the Tokens tab, use the **"…"** menu next to the token to **Regenerate** (issues a new secret for the same token record) or **Delete** (permanently revokes it). Deletion can also be done via DigitalOcean's Revoke Token Flow API endpoint.
- **Common errors and resolutions**:
  - Token created with **Read Only** scope by mistake → TXT record creation fails with 403; scopes cannot be edited after creation, so generate a new token with `domain:create`/`domain:delete` or Full Access instead.
  - Token has an expiration date set and it lapsed → requests fail with 401; generate a replacement token and update the plugin config.
  - Admin is a member of multiple DigitalOcean teams and generated the token under the wrong team context → the token can't see the intended domain because it lives under a different team; switch to the correct team space before generating.
  - Hitting DigitalOcean's API rate limits during a burst of validation requests → transient failures; check the `X-RateLimit-*` response headers.
- **Terms of Service / Privacy Policy**: Terms https://www.digitalocean.com/legal/terms-of-service-agreement — Privacy https://www.digitalocean.com/legal/privacy-policy. Re-confirmed live: the Terms URL loads correctly (page title "Terms of Service Agreement," last updated 22 Aug 2026) — the correction from the old `/legal/tos` path noted in the plugin's history is still good.
- **Evidence/verification status**: Verified live by directly fetching DigitalOcean's own docs pages (create-personal-access-token, scopes reference) and the Terms of Service page itself.

---

## DNSimple

- **Credential-creation link**: Account page → **"API & Access"** tab (left sidebar) → click **"Add"** → name the token → click **"Generate token."** Verified directly against DNSimple's own support article at https://support.dnsimple.com/articles/api-access-token/.
- **Minimum required permission/scope**: On the **Teams plan or higher**, create a scoped token with the **"Zones"** resource category set to **"Full access"** (not "Read only"), restricted to **"selected resources"** = just the one domain used for DNS-01. Leave Certificates, Domains, and Registrar categories unselected — they aren't needed to create/delete TXT records. On the **Solo plan**, there is no scoping UI at all; every token automatically has full permissions on the whole account.
- **Zone/project scoping guidance**: Yes, on Teams+ plans — a token's Zones access can be limited to "selected resources" (i.e., one specific domain) rather than "all resources." On Solo plan, scoping is unavailable, so full-account access is unavoidable unless the account is upgraded.
- **Field-by-field mapping**:
  - `api_token` (Account access token) → the token string shown once immediately after clicking "Generate token" in the API & Access tab.
- **Credential rotation/revocation guidance**: In the API & Access tab's token list, click the trash-can **Delete** icon next to the token to revoke it; generate a new one the same way and update the plugin's stored value.
- **Common errors and resolutions**:
  - Token's Zones scope was left at "Read only" → TXT record creation fails with 403; regenerate with "Full access" for Zones.
  - Token scoped to "selected resources" but the wrong domain was chosen → the intended zone returns 403/404; edit the scope (or regenerate) with the correct domain selected.
  - Admin assumes their Solo-plan token is scoped to one domain when it isn't → Solo-plan tokens are always full-account; upgrade to Teams if narrower scoping is required.
  - An OAuth application token/client credential was configured instead of a personal Account Access Token → different auth flow; the plugin expects a plain bearer token string from API & Access, not OAuth client ID/secret.
- **Terms of Service / Privacy Policy**: Terms https://dnsimple.com/terms — Privacy https://dnsimple.com/privacy
- **Evidence/verification status**: Verified live by directly fetching support.dnsimple.com/articles/api-access-token/ and blog.dnsimple.com/2023/11/scoped-access-tokens/ (DNSimple's own support site and blog).

---

## DNS Made Easy

- **Credential-creation link**: https://cp.dnsmadeeasy.com/account/info — reached via **Config tab → Account Information** in the DNS Made Easy control panel. (DNS Made Easy's own support article at support.dnsmadeeasy.com returned HTTP 403 to automated fetch on every attempt, including via a DigiCert knowledge-base mirror that redirected back to the same blocked host — consistent with bot/WAF protection on their help center. This URL and workflow are corroborated by multiple independent search-result snippets quoting that same official article, but were not read directly.)
- **Minimum required permission/scope**: **Not independently verified — confirm before publishing.** No evidence surfaced of any granular permission/role system for DNS Made Easy API credentials — the account issues a single account-wide API Key + Secret Key pair with no documented scoped-role or per-domain permission model.
- **Zone/project scoping guidance**: **Not independently verified — confirm before publishing** for an authoritative statement, but available evidence strongly points to account-wide access being unavoidable: DNS Made Easy issues one API Key/Secret Key pair per account (not per domain), and no mechanism to restrict that pair to a single domain was found in the available documentation excerpts.
- **Field-by-field mapping**:
  - `api_key` (API Key) → shown at the bottom of the Account Information page (Config tab) once generated.
  - `secret_key` (Secret Key) → shown alongside the API Key on the same page; both are generated together via a single checkbox-and-Save action.
- **Credential rotation/revocation guidance**: **Not independently verified — confirm before publishing** for the exact control label, but corroborated: re-checking the "generate API key" checkbox on the Account Information page and saving again issues a new Key/Secret pair, immediately invalidating the previous one. There does not appear to be a separate "revoke" action distinct from regeneration.
- **Common errors and resolutions**:
  - Account is on a plan below **Business / DNS-25** → the API Key section doesn't appear on the Account Information page at all; upgrade the plan (API access requires Business/DNS-25 or above).
  - Logged in as a non-primary user on the account → the API Key/Secret fields are invisible even on a qualifying plan; only the primary account user can view/generate them.
  - API Key and Secret Key values transposed when copying → authentication failures; re-copy each value from its own labeled field.
  - Regenerating credentials without updating the plugin afterward → the old pair stops working immediately ("access denied") since regeneration invalidates the previous pair right away.
- **Terms of Service / Privacy Policy**: Terms https://www.digicert.com/legal-repository — Privacy https://privacy.digicert.com/policies/en/?name=dns-network-security-products-privacy-notice
- **Evidence/verification status**: Could not fetch dnsmadeeasy.com's own support pages directly (403 on every attempt). All DNS Made Easy findings above are corroborated via consistent, repeated third-party/search-cached quotations of the same official article rather than independently verified by directly reading the primary source — flagged accordingly per field.

---

## DNSPod

- **Credential-creation link**: https://console.dnspod.cn/account/token/token — reached via **Account Center (账户中心) → Key Management (密钥管理) → "Create Key" (创建密钥)**. Verified directly against DNSPod's own documentation at https://docs.dnspod.cn/account/dnspod-token/.
- **Minimum required permission/scope**: **Not independently verified — confirm before publishing** for any granular scope, because none appears to exist for this credential type. Evidence gathered indicates the legacy **DNSPod Token** (`token_id` + `token`, used to call DNSPod's API 2.0 — which is the credential type this plugin's driver uses) carries the primary account's full DNSPod permissions with no domain-level or role-based restriction available. (Fine-grained CAM permission policies exist only for the separate Tencent Cloud API key credential type — SecretId/SecretKey, API 3.0 — which is not the field pair this plugin asks for.)
- **Zone/project scoping guidance**: No — account-wide access is unavoidable with the legacy Token this plugin uses. The only way to narrow this within DNSPod's own model is to switch to Tencent Cloud API 3.0 keys with a CAM policy restricted to one domain, but that is a different credential pair (SecretId/SecretKey) than `token_id`/`token`, so it does not apply to this plugin's existing fields.
- **Field-by-field mapping**:
  - `token_id` (Token ID) → the numeric ID shown before the comma when a key is created, e.g. the `13490` in `13490,6b5976c68aba5b14a0558b77c17c3932`.
  - `token` (Token) → the string after the comma in that same one-time display, e.g. `6b5976c68aba5b14a0558b77c17c3932`.
- **Credential rotation/revocation guidance**: Log into the DNSPod console → Account Center → Key Management, locate the key in the list, and delete it there; then create a replacement key the same way. (The exact delete-button label was not independently verified — confirm before publishing — as the fetched documentation covered creation but not the deletion screen in detail.)
- **Common errors and resolutions**:
  - Pasting the combined `"ID,Token"` string into a single field instead of splitting it → authentication failures; split at the comma into the plugin's separate `token_id` and `token` fields.
  - Using a sub-account's token → sub-accounts have no resource access by default under the legacy API, causing permission errors; use the primary account's token, or grant the sub-account explicit permissions first.
  - Confusing a DNSPod Token with Tencent Cloud SecretId/SecretKey → the wrong credential type is entered into the `token_id`/`token` fields, causing signature/format errors, since this plugin's driver expects the legacy Token format specifically.
  - Using the international DNSPod.com console (a different product/company) instead of the mainland `dnspod.cn` console → generates credentials for the wrong system entirely; they will not authenticate against `api.dnspod.cn`.
- **Terms of Service / Privacy Policy**: Terms https://docs.dnspod.cn/account/terms-of-service/ — Privacy https://docs.dnspod.cn/account/privacy-policy/
- **Evidence/verification status**: Verified live by directly fetching docs.dnspod.cn/account/dnspod-token/ for the creation flow, console URL, and token format. The absence of a permission-scoping option and the exact deletion-screen wording are corroborated via search only, not independently confirmed by fetching a deletion-specific page.

---

## Domeneshop

- **Credential-creation link**: https://domene.shop/admin?view=api — verified directly against Domeneshop's own official API documentation at https://api.domeneshop.no/docs/, which names this URL and describes the credential model (see below).
- **Minimum required permission/scope**: **Not independently verified — confirm before publishing.** Domeneshop's own API documentation does not describe any granular permission/role system; a generated token/secret pair appears to grant full account API access (domains, DNS records, invoices, domain forwards, DDNS updates), with no scoped or read-only mode documented.
- **Zone/project scoping guidance**: No — account-wide access is unavoidable. Domeneshop's documentation gives no mechanism to restrict a token/secret pair to a single domain; a single credential pair can manage every domain, DNS record, and (per the docs) even invoices/billing on the account. Tradeoff: if isolation matters, the only option is using a separate Domeneshop account dedicated to the domain used with this plugin.
- **Field-by-field mapping**:
  - `token` (API token) → the "Token" value (used as the HTTP Basic Auth **username**), shown once when generated at domene.shop/admin?view=api.
  - `secret` (API secret) → the "Secret" value (used as the HTTP Basic Auth **password**), shown alongside the token at the same time.
- **Credential rotation/revocation guidance**: **Not independently verified — confirm before publishing** for the exact revoke/delete control's label. The admin page at domene.shop/admin?view=api is confirmed as where credentials are generated; by convention such pages also list and allow removal of existing credential pairs, but that specific control was not visible in the content retrievable via automated fetch.
- **Common errors and resolutions**:
  - Sending the secret as a bearer token or query parameter instead of HTTP Basic Auth (token as username, secret as password) → 401 Unauthorized; use Basic Auth exactly as documented.
  - Regenerating credentials without updating the plugin's stored values → silent authentication failures on the next certificate renewal attempt.
  - Because the credential is account-wide (including billing/invoices), reusing the same token/secret across multiple unrelated integrations increases blast radius if leaked → use a dedicated pair for this plugin only.
  - Domain not active/verified in the Domeneshop account, or moved to a different account → the API returns a domain-not-found style error even with otherwise-valid credentials.
- **Terms of Service / Privacy Policy**: https://domene.shop/terms
- **Evidence/verification status**: Verified live by directly fetching api.domeneshop.no/docs/ (Domeneshop's own official docs), confirming the auth model (Basic Auth, token=username/secret=password) and the admin URL for generating credentials. The absence of permission scoping and the exact revoke-control wording are not independently confirmed — the docs simply don't mention scoping at all, which is evidence of absence but not a directly fetched negative statement.

---

## DreamHost DNS

- **Credential-creation link**: https://panel.dreamhost.com/?tree=home.api — verified directly against DreamHost's own knowledge base article at https://help.dreamhost.com/hc/en-us/articles/4407354972692-Connecting-to-the-DreamHost-API.
- **Minimum required permission/scope**: On the API key creation page, under **"Functions this key should have access to,"** check only the specific `dns-*` function checkboxes actually needed — at minimum `dns-add_record` and `dns-remove_record` (add `dns-list_records` if the plugin reads existing records first) — rather than the **"All functions!"** checkbox. This matches the plugin's own field description ("API key with dns-* function access") directly: DreamHost's permission model is a per-function checklist, and dns-* is exactly the checklist row group to use.
- **Zone/project scoping guidance**: **Not independently verified — confirm before publishing** for a definitive statement, but available evidence (a function-level checklist with commands like `dns-add_record` applying account-wide) indicates DreamHost scopes API keys by **function/command**, not by domain — a key granted `dns-add_record` can add records to any domain in the account. Tradeoff: there is no documented per-domain isolation; the narrowest available control is limiting the key to only the `dns-*` functions.
- **Field-by-field mapping**:
  - `api_key` (API key with dns-* function access) → the key string displayed after clicking **"Generate a new API Key now!"** on the panel's API page, once only the required `dns-*` function checkboxes have been checked.
- **Credential rotation/revocation guidance**: **Not independently verified — confirm before publishing** for the exact revoke-control label on the general Web Panel API key list. (A "Remove Key" control is documented for the separate DreamObjects key list at help.dreamhost.com/hc/en-us/articles/215986357, but the equivalent control for general API keys on the `home.api` panel page was not directly confirmed via fetch.)
- **Common errors and resolutions**:
  - Checking **"All functions!"** instead of only the `dns-*` boxes → works but grants far more access than needed (hosting, billing, email, etc.); regenerate with only the required `dns-*` functions checked.
  - Key generated under a subaccount that lacks the relevant billing/account privileges → the key may not have visibility into DNS for a domain owned by a different (sub)account; generate the key from the account that actually owns the domain.
  - Checking only `dns-list_records` without also checking `dns-add_record`/`dns-remove_record` → the plugin can see existing records but DNS-01 validation record creation/cleanup silently fails; add the missing record-management functions.
  - Copying the key with surrounding whitespace or a trailing newline from the panel → silent authentication failures; re-copy carefully.
- **Terms of Service / Privacy Policy**: Terms https://www.dreamhost.com/legal/terms-of-service/ — Privacy https://www.dreamhost.com/legal/privacy-policy/
- **Evidence/verification status**: Verified live by directly fetching help.dreamhost.com/hc/en-us/articles/4407354972692, confirming the panel URL and the per-function checklist creation model. Zone-scoping absence and the exact revoke-button label on the general API key page are corroborated via search only (the only directly-fetched "remove key" instructions found were for the differently-scoped DreamObjects key type), not independently confirmed for the general API key list itself.

---


## Dynu

- **Credential-creation link**: https://www.dynu.com/ControlPanel/APICredentials (the "API Credentials" area of the Dynu control panel; requires login — verified the URL resolves to an authentic `dynu.com` control-panel login redirect, not a 404 or unrelated page).
- **Minimum required permission/scope**: Dynu's simple API Key model has no granular scopes — the key is a single account-wide credential authenticated via an `API-Key:` request header. There is no separate "DNS-only" role; the alternative OAuth2 flow (`client_id`/`secret`) is similarly account-wide (its issued token scope string spans Domain, DNS, and Email APIs together). Since the plugin's field is a single `api_key`, it is using Dynu's simple API Key auth, not OAuth2.
- **Zone/project scoping guidance**: Not possible to scope to a single domain/zone — the API key grants access to all DNS zones (and other Dynu services, e.g. Dynamic DNS hostnames) on the account. Tradeoff: convenience of one key vs. broader blast radius if leaked; mitigate by using a dedicated Dynu account that holds only the domain(s) SAM manages, if feasible.
- **Field-by-field mapping**:
  - `api_key`: Log into the Dynu control panel → **API Credentials** page (https://www.dynu.com/ControlPanel/APICredentials) → the API Key is shown/generated there (create, reset, or clear it from this same screen).
- **Credential rotation/revocation guidance**: On the same API Credentials page, the key can be reset (issues a new key, invalidating the old one immediately) or cleared entirely. There is no separate "revoke" vs "rotate" — resetting is both.
- **Common errors and resolutions**:
  - *401/invalid key after "reset"*: resetting the key immediately invalidates the old value — update the plugin's stored `api_key` right after resetting, or DNS-01 renewals will fail until updated.
  - *TXT record not found by validator*: Dynu's nameservers must actually be authoritative for the domain (domain delegated to Dynu NS) — if the domain uses another registrar's DNS, Dynu-hosted records won't be queried by the ACME validator.
  - *Confusing the API key with the OAuth client_id/secret*: some Dynu community/API examples reference OAuth2 `client_id`+`secret`; the plugin's single `api_key` field expects the simple `API-Key` header credential from the same API Credentials page, not the OAuth pair.
- **Terms of Service / Privacy Policy**: Terms https://www.dynu.com/en-US/Legal/TermsOfUse — Privacy https://www.dynu.com/en-US/Legal/PrivacyPolicy
- **Evidence/verification status**: Verified live: the API Credentials URL resolves to an authentic Dynu control-panel page (login-gated, as expected for an account-specific page). Corroborated via search (Dynu's own support/API documentation pages and community forum posts, consistent across multiple independent sources): the "API-Key" simple-auth header format, the account-wide/non-scoped nature of the key, and the reset/clear mechanism. No fields required "Not independently verified."

---

## easyDNS

- **Credential-creation link**: https://cp.easydns.com/ (easyDNS control panel login) → under **User → Security**, scroll to the **REST API** section at the bottom of the page.
- **Minimum required permission/scope**: easyDNS's REST API credentials (token + key) are account-wide — there is no documented per-zone or per-record-type permission scope. The token/key pair authenticates as the account holder for all REST API operations available to that account, including DNS zone/record management.
- **Zone/project scoping guidance**: Not possible to scope to a single domain — the credential pair applies to the whole easyDNS account and every zone in it. If SAM should only touch one domain, the safest mitigation is a dedicated easyDNS account/sub-account holding only that domain, since the API itself offers no scoping switch.
- **Field-by-field mapping**:
  - `token`: found in the control panel at **User → Security → REST API** section — the "User Token" value (functions like a username/public identifier for the API, but should still be kept confidential).
  - `key`: also in **User → Security → REST API** — click **Regenerate** to generate/display the API Key. It is shown only once immediately after generation and cannot be retrieved again afterward, so it must be copied into the plugin's `key` field at that time.
- **Credential rotation/revocation guidance**: Click **Regenerate** again on the REST API section to issue a new key, which immediately invalidates the previous one (functions as both rotation and revocation). New accounts are first issued sandbox credentials; live/production credentials are issued separately after a signup/registration step for full REST API access.
- **Common errors and resolutions**:
  - *Requests succeed against sandbox but fail in production (or vice versa)*: easyDNS issues separate sandbox and live credentials/endpoints — confirm the plugin is pointed at the live REST API host and using the live-issued token/key, not sandbox ones.
  - *401 after regenerating the key*: the old key stops working the instant "Regenerate" is clicked and the new key is only shown once — if it wasn't copied immediately, it must be regenerated again and re-saved into the plugin.
  - *TXT record changes not reflected*: confirm the domain's nameservers are actually delegated to easyDNS; the REST API only manages zones easyDNS is authoritative for.
- **Terms of Service / Privacy Policy**: Terms https://easydns.com/legal/terms-of-service/ — Privacy https://easydns.com/legal/privacy-policy/
- **Evidence/verification status**: Verified live: cp.easydns.com is confirmed as the real control-panel login domain, and the REST API documentation/Swagger site (docs.sandbox.rest.easydns.net) is confirmed live. Corroborated via search/multiple independent sources (easyDNS's own blog posts, GitHub repos for their certbot plugin and MCP server, and third-party ACME client docs such as Posh-ACME and lego) for the "User → Security → REST API", "Regenerate" one-time-display behavior, and the lack of granular scoping. Not independently verified via a direct authenticated fetch of the REST API panel itself (it requires login), but corroboration is consistent across every independent source checked.

---

## Gandi DNS

- **Credential-creation link**: https://admin.gandi.net/ → sign in → top-right **username menu → User Settings** (or organization **Sharing** tab) → **Personal Access Tokens** → **Create a token**. (Equivalent direct settings path: account.gandi.net security/authentication options.)
- **Minimum required permission/scope**: **"Manage domain technical configurations"** — this is the permission family that covers LiveDNS (DNS record) read/write access; per Gandi's own documentation it also implies "See and renew domain names" (a required corollary, not an extra grant you need to separately enable).
- **Zone/project scoping guidance**: Yes — when creating the token, use **"Restrict to selected products"** (also described as scoping to "Specific domains") to limit the token to only the domain(s) SAM manages, rather than the whole Gandi organization/account. This is the recommended approach; leaving it unrestricted grants the permission across every domain in the account/organization.
- **Field-by-field mapping**:
  - `api_token` (Personal Access Token, `pat-...`): shown once at creation time on the **Create a token** screen in admin.gandi.net — copy it immediately, as Gandi does not display it again afterward.
- **Credential rotation/revocation guidance**: Revoke instantly from either **Organizations → [org] → Sharing tab → trashcan icon** next to the token, or **Username → Settings → Personal Access Tokens (PAT) → View my personal access tokens → trashcan icon**. Deletion takes effect immediately. There is no in-place "rotate" — create a new token with the desired scope/expiration and delete the old one. Tokens can also be given a fixed expiration at creation (7/30/60/90 days or 1 year) as a built-in rotation forcing-function.
- **Common errors and resolutions**:
  - *403/permission denied on record updates*: the token was created without "Manage domain technical configurations," or was scoped to different domains than the one SAM is validating — recheck both the permission and the domain restriction list.
  - *Token stopped working after its expiration date*: Gandi PATs can be set to auto-expire (7 days up to 1 year) — if the plugin suddenly gets consistent auth failures, check whether the token's expiration date passed and issue a new one.
  - *Token created under the wrong organization*: if the domain lives under a specific Gandi organization (not the personal account), the token must be created/scoped from that organization's Sharing tab, or it won't see the domain at all.
- **Terms of Service / Privacy Policy**: Terms https://www.gandi.net/en/contracts/terms-of-service — Privacy https://www.gandi.net/en/contracts/privacy-policy
- **Evidence/verification status**: Verified live: the PAT creation/management flow at docs.gandi.net's own "Manage API accesses with a Personal Access Token" page (fetched directly). The specific permission label **"Manage domain technical configurations"** and the "Restrict to selected products"/"Specific domains" scoping option are corroborated via consistent independent search results (Gandi's own help center and third-party integration guides) rather than a direct fetch of the live admin.gandi.net token-creation screen (which requires authentication and could not be fetched directly) — treat the exact wording as corroborated, not screen-verified, and confirm the literal checkbox label in the live UI before publishing if pixel-exact wording matters.

---

## GleSYS

- **Credential-creation link**: https://cloud.glesys.com/ → log in → click your profile name (upper-right corner) → **Control API access** → **Create** (green button).
- **Minimum required permission/scope**: GleSYS API keys default to **denied access to all functions for all hosts** until explicitly changed. For DNS-01, the documented minimum in GleSYS's own Let's Encrypt DNS-01 guide is to open the key's **Permissions** (Actions → Permissions) and set the **Domain** row to **Allowed**. GleSYS's function list does expose granular function names (e.g. `domain/addrecord`, `domain/updaterecord`, `domain/deleterecord`, `domain/listrecords`), but the permissions UI itself grants/denies at the **Domain** function-group level rather than exposing a narrower "DNS-record-only" toggle — so "Domain: Allowed" is the narrowest currently-documented UI-level grant.
- **Zone/project scoping guidance**: The API key can be restricted to specific **allowed hosts (IP addresses)** that may use it, which limits exposure if the key leaks, but the **Domain** permission itself is not documented as scopable to a single domain — enabling it grants management of all domains under that GleSYS account. Tradeoff: host/IP allowlisting reduces the attack surface (only SAM's server IP can use the key) even though the domain permission itself remains account-wide.
- **Field-by-field mapping**:
  - `account` (Account number, `CL12345`): the "CL" project identifier shown in the GleSYS Cloud interface (e.g., in the project switcher / URL when viewing your cloud project); GleSYS's own API responses return it as the `cloudaccount`/project id alongside a separate numeric `customernumber`. Note: exact on-screen label/position for where this is displayed at a glance was not confirmed via a live authenticated screenshot — corroborated only as "visible in the control panel/API responses," so **confirm before publishing** exactly which label in the UI the admin should read for the `CL12345` value.
  - `api_key`: generated on the **Control API access** page (from your profile menu) via the **Create** button; the key value is displayed there once created.
- **Credential rotation/revocation guidance**: On the **Control API access** page, existing keys can be deleted/disabled from the same list where they were created (via the key's Actions menu); create a replacement key and update the plugin, then delete the old key to complete rotation.
- **Common errors and resolutions**:
  - *Permission denied on record create/update*: the API key's **Domain** function permission is still set to its default-denied state — open Actions → Permissions and set Domain to Allowed.
  - *Key works from one server but not another*: GleSYS keys can be restricted to specific allowed host IPs — if SAM's outbound IP changed (e.g., new server, load balancer, NAT), the key's allowed-hosts list needs updating.
  - *Using the customer number instead of the CL account/project id (or vice versa)*: GleSYS distinguishes a numeric `customernumber` from the `CL...`-prefixed cloud project id — the plugin's `account` field expects the `CL12345`-style project id, not the bare customer number.
- **Terms of Service / Privacy Policy**: Terms https://glesys.com/legal/general-terms-and-conditions/ — Privacy https://glesys.com/legal/privacy-policy/
- **Evidence/verification status**: Verified live via direct fetch of GleSYS's own real-world use-case doc, "Let's Encrypt DNS-01 challenge using Glesys API and Dehydrated" (docs.glesys.com) — this confirmed the Control API access → Create flow and the Domain=Allowed permission step. Corroborated via search (GleSYS's own API-docs GitHub wiki) for the function-name list and default-denied posture. **Not independently verified**: the exact on-screen location/label for finding the `CL12345` account number outside of API responses — flagged inline above; confirm this specific UI detail before publishing.

---

## GoDaddy DNS

- **Credential-creation link**: https://developer.godaddy.com/keys (classic API Key/Secret creation page; GoDaddy's docs also reference a `classic-developer.godaddy.com/keys` variant of this same classic-keys page during their ongoing PAT migration — see note below).
- **Minimum required permission/scope**: The classic API Key/Secret pair (the `sso-key` model, which is what the plugin's `api_key`/`api_secret` fields correspond to) is **not permission-scoped at all** — it grants the same access as the account itself across the Domains/DNS API. GoDaddy is in the process of introducing **Personal Access Tokens (PATs)** with granular `api.resource:operation`-style capability scopes as the forward-looking replacement, but the plugin's two-field (key+secret) form matches the classic `sso-key` credential, not the newer single-token PAT.
- **Zone/project scoping guidance**: Not possible — the classic key/secret is account-wide, covering every domain on the GoDaddy account, with no per-domain restriction available. As of April 2026, GoDaddy lowered the domain-count gate for generating API credentials from "10+ domains" to any account with at least 1 domain, so a single-domain account can now use this without workarounds — but the resulting credential still isn't scoped to just that domain if more are later added to the account.
- **Field-by-field mapping**:
  - `api_key`: Sign in at developer.godaddy.com/keys → **Create New API Key** → name it → select **Production** as the environment → the **API Key** is displayed on save.
  - `api_secret`: shown alongside the API Key at the same time, on the same **Create New API Key** confirmation screen — GoDaddy displays the secret only once; it cannot be retrieved again afterward.
- **Credential rotation/revocation guidance**: Return to developer.godaddy.com/keys, and delete/deactivate the existing key from the key list, then create a new Production key/secret pair and update the plugin. There is no separate "regenerate the secret only" option for a given key — rotation means creating a new key and removing the old one.
- **Common errors and resolutions**:
  - *ACCESS_DENIED using a Production key*: confirm the key was created with environment **Production** (not OTE/test) — a test-environment key only works against `api.ote-godaddy.com`, not the live `api.godaddy.com` endpoint the plugin uses.
  - *401 with a key that used to work*: GoDaddy is deprecating the classic `sso-key` (key+secret) authentication model in 2026 in favor of PATs — if authentication that previously worked suddenly fails account-wide, check GoDaddy's developer announcements for a forced cutover date, since a plugin update to PAT-based auth may become necessary.
  - *Bot/WAF block when trying to reach the developer portal directly*: godaddy.com and its developer subdomains have been observed returning HTTP 403 or redirecting to an SSO login wall for automated (non-browser) requests — an administrator should complete key creation via a normal logged-in browser session, not a script.
- **Terms of Service / Privacy Policy**: Terms https://www.godaddy.com/legal/agreements/universal-terms-of-service-agreement — Privacy https://www.godaddy.com/agreements/privacy
- **Evidence/verification status**: Could not verify by direct fetch — as anticipated, developer.godaddy.com/keys and classic-developer.godaddy.com/keys both either returned an SSO login redirect or a bot-protection wall when fetched directly in this research session (consistent with the previously-noted GoDaddy 403/bot-protection behavior on this project). All details above (the classic key/secret creation flow, Production vs. OTE environment selection, one-time secret display, the single-domain-account change in April 2026, and the 2026 sso-key deprecation-in-favor-of-PATs) are **corroborated via web search** against GoDaddy's own developer documentation pages (developer.godaddy.com/en/docs/api-users/auth and .../how-godaddy-apis-work, plus GoDaddy's own news/blog post), not directly screen-verified. Treat exact button labels as corroborated, not screen-confirmed, and note the sso-key deprecation timeline explicitly to the admin since it may affect this plugin's GoDaddy driver going forward.

---

## Google Cloud DNS

- **Credential-creation link**: https://console.cloud.google.com/iam-admin/serviceaccounts (Google Cloud Console → **IAM & Admin → Service Accounts**).
- **Minimum required permission/scope**: Google's built-in **`roles/dns.admin`** ("DNS Administrator") role is the narrowest *predefined* role that includes both `dns.resourceRecordSets.create`/`update`/`delete` and `dns.changes.create` — the specific permissions needed to create/update/delete TXT records for DNS-01. For tighter least-privilege, a **custom IAM role** can be created containing only `dns.changes.create`, `dns.changes.get`, `dns.resourceRecordSets.create`, `dns.resourceRecordSets.delete`, `dns.resourceRecordSets.get`, `dns.resourceRecordSets.list`, and `dns.managedZones.get`/`list` (needed to resolve the zone) — narrower than the predefined `roles/dns.admin`/`roles/dns.editor` roles, which also grant managed-zone create/delete and other administrative actions not needed for ACME validation.
- **Zone/project scoping guidance**: Cloud DNS supports IAM permission bindings at the **individual managed zone** level, not just the project level — so the service account's role can be granted only on the specific managed zone containing the domain SAM validates, rather than project-wide. This is the recommended approach when the GCP project hosts other DNS zones that should stay untouched by this credential.
- **Field-by-field mapping**:
  - `service_account_json`: Google Cloud Console → **IAM & Admin → Service Accounts** → **Create Service Account** (name it, e.g. "sam-dns01") → grant it the DNS role (project-level `roles/dns.admin`, or skip project-level rights and grant zone-level access afterward via the managed zone's own permissions page) → open the created service account → **Keys** tab → **Add Key → Create new key** → choose **JSON** → **Create**. The browser downloads a `.json` key file — paste its full contents into the plugin's textarea field.
- **Credential rotation/revocation guidance**: On the service account's **Keys** tab, click **Delete** next to the old key to revoke it, and **Add Key → Create new key (JSON)** to issue a replacement — Google recommends rotating user-managed service account keys at least every 90 days. Note that deleting the key does not revoke already-issued short-lived tokens derived from it; to fully cut off access immediately, disable or delete the service account itself.
- **Common errors and resolutions**:
  - *PERMISSION_DENIED on record changes*: the service account's role lacks `dns.resourceRecordSets.create`/`dns.changes.create` on the target zone — verify the role binding (project-level `roles/dns.admin`, or a zone-level custom-role binding) actually includes the zone in question.
  - *Key pasted incorrectly / "invalid_grant" or JSON parse errors*: the downloaded key file must be pasted in full (including the surrounding `{...}` JSON object, not just the `private_key` value) — a common mistake is copying only part of the file.
  - *Correct role granted at the wrong level*: granting `roles/dns.admin` on the wrong project (if the organization has several GCP projects) or on a different zone than the one actually delegated for the domain will silently fail validation — confirm the managed zone's DNS name matches the domain being validated.
  - *Downloaded key can't be retrieved again*: Google only allows downloading the private key material once, at creation time — if lost, the old key must be deleted and a new one created (not "re-downloaded").
- **Terms of Service / Privacy Policy**: Terms https://cloud.google.com/terms — Privacy https://cloud.google.com/terms/cloud-privacy-notice
- **Evidence/verification status**: Verified live via direct fetch of Google's own current Cloud DNS "Roles and permissions" documentation (docs.cloud.google.com/dns/docs/access-control) and IAM service-account creation/key documentation (docs.cloud.google.com/iam/docs/service-accounts-create, .../keys-create-delete, .../key-rotation) — role names, permission strings, zone-level IAM scoping, and key lifecycle behavior are all confirmed directly against current Google documentation.

---

## Hetzner DNS

- **Credential-creation link**: https://console.hetzner.com/ → sign in → **Security** (left menu) → **API tokens** (upper menu) → **Generate API token**.
- **IMPORTANT — platform migration note**: Hetzner's legacy **DNS Console** (`dns.hetzner.com`) and its separate DNS API/token system were **shut down in May 2026** (new-zone creation was disabled from November 10, 2025, remaining zones were auto-migrated in April 2026, and `dns.hetzner.com` now redirects to `console.hetzner.com`). Tokens created in the old DNS Console do not work with the new unified Hetzner Console/Cloud API. As of today, DNS zones and their API tokens are managed exclusively through the unified **Hetzner Console**, so this is the only current, correct path for a new setup.
- **Minimum required permission/scope**: When generating a token, choose the **"Read & Write"** permission level (the alternative, "Read," is insufficient since DNS-01 requires creating and deleting TXT records). Hetzner does not expose a narrower "DNS-only" permission tier below Read/Read & Write on the token itself.
- **Zone/project scoping guidance**: In the unified Hetzner Console, API tokens are scoped **per project**, and DNS zones live inside projects — so a token generated in a project containing only the one domain SAM manages is effectively scoped to that domain, by placing it in its own dedicated project and generating the token there. If the zone shares a project with other zones or Cloud resources (servers, load balancers, etc.), the Read & Write token will also grant management access to those other resources in the same project — plan project layout accordingly for isolation.
- **Field-by-field mapping**:
  - `api_token` (DNS API Token): Hetzner Console → select the project containing the DNS zone → **Security → API tokens → Generate API token** → enter a description → select **Read & Write** → the token value is shown once immediately after generation.
- **Credential rotation/revocation guidance**: On the same **Security → API tokens** page, use the **revoke**/delete action next to an existing token to invalidate it immediately, then generate a new token and update the plugin. The token cannot be viewed again once its creation dialog is closed, so if it's lost, it must be revoked and replaced rather than retrieved.
- **Common errors and resolutions**:
  - *Old (pre-migration) token suddenly stops working*: any token issued from the legacy `dns.hetzner.com` DNS Console is no longer valid after the May 2026 shutdown — a new token must be generated from console.hetzner.com.
  - *401/403 despite a valid-looking token*: the token was generated with only **Read** permission — regenerate with **Read & Write**.
  - *Token works for one zone but plugin also needs another zone in a different project*: since tokens are project-scoped, a token from Project A cannot manage a zone that lives in Project B — either move the zone or generate a second token for the other project.
  - *Zone not found / empty zone list via API*: confirm the domain's zone was actually migrated into a project after the DNS Console shutdown, rather than still sitting unmigrated (should no longer occur post-May 2026, but worth checking on very old, dormant zones).
- **Terms of Service / Privacy Policy**: Terms https://www.hetzner.com/legal/terms-and-conditions/ — Privacy https://www.hetzner.com/legal/privacy-policy/
- **Evidence/verification status**: Verified live via direct fetch of Hetzner's own documentation (docs.hetzner.com/cloud/api/getting-started/generating-api-token/ for the Security → API tokens → Generate API token flow and Read/Read & Write permission choice) and via search corroboration against Hetzner's own status page incident announcements (status.hetzner.com) for the precise DNS Console shutdown timeline (Nov 2025 new-zone freeze, April 2026 auto-migration, May 2026 shutdown/redirect) and Hetzner's own docs (docs.hetzner.com/networking/dns/migration-to-hetzner-console/features-and-differences/) for per-project token scoping. This migration-timeline finding is time-sensitive and directly relevant: any pre-existing SAM documentation referencing the old `dns.hetzner.com` DNS Console token flow is now obsolete.

---


## INWX

- **Credential-creation link**: Log in at https://www.inwx.com/en/customer/login and create a restricted subaccount from the account/subaccount management area of the customer interface. (The exact menu label/path for subaccount creation inside the logged-in customer area could not be loaded directly — see Evidence status below.)
- **Minimum required permission/scope**: INWX subaccounts support a role-based permission model; the role documented (by INWX's own API client maintainers and by the widely-used `certbot-dns-inwx` plugin) as the correct minimum for DNS-01 use is **"DNS management"** (sometimes shown as "Nameserver"/DNS role in the subaccount permission list). This should be narrower than the full/master account role, which also grants domain transfer, billing, and registration rights.
- **Zone/project scoping guidance**: INWX's subaccount roles are account-wide feature-based (e.g. "can manage DNS") rather than zone-scoped to a single domain — a "DNS management" subaccount can typically manage DNS for every domain on the account, not just one. If true single-zone isolation is required, the only INWX-side mitigation is running one full INWX customer account per domain, which is usually impractical; the realistic tradeoff is: use a dedicated DNS-only subaccount (blast radius = all zones on that account, but no billing/registrar/transfer risk) rather than the primary account's credentials.
- **Field-by-field mapping**:
  - `username` — the login name of the INWX (sub)account created for API use, shown on the subaccount's own login page/subaccount list.
  - `password` — the password set when creating that subaccount (or the main account password if no subaccount is used — not recommended). If the account has 2FA enabled, the plugin driver must be able to supply a TOTP-derived TAN; this is a per-login OTP, not a static password field, so many admins instead create the API subaccount with 2FA disabled specifically to keep the credential a static username/password pair.
- **Credential rotation/revocation guidance**: Rotate by changing the subaccount's password from the INWX customer interface, or delete the subaccount outright to instantly revoke all API access tied to it (preferred over rotating the primary account password, which would also affect interactive login).
- **Common errors and resolutions**:
  - Login succeeds interactively but the API call fails — the account likely has 2FA enabled and the driver was given a static password instead of a TOTP secret/TAN; create a dedicated subaccount with 2FA disabled for API use.
  - "Permission denied" on record creation — the subaccount role is too narrow (e.g. read-only) or missing the DNS role entirely; edit the subaccount's role to include DNS/nameserver management.
  - Intermittent auth failures — TOTP-based logins cannot be reused within the same 30-second window; if 2FA is enabled on the API-use account, back-to-back renewal attempts in the same cycle can collide.
  - Wrong domain not found — the subaccount's DNS role may be scoped away from the target domain if INWX's permission UI allows domain-list restriction on that role; check the subaccount's assigned domain list.
- **Terms of Service / Privacy Policy**: Terms https://www.inwx.com/en/aboutus/terms — Privacy https://www.inwx.com/en/aboutus/dataprotection
- **Evidence/verification status**: Corroborated via search (not live-fetched from INWX's own logged-in UI, which requires an account and could not be loaded): the "DNS management" subaccount role name is quoted directly from the `certbot-dns-inwx` plugin README (a maintained third-party document, not INWX's own page) and repeated across multiple independent DNS-integration READMEs (DNSControl, Posh-ACME), giving reasonable confidence it is accurate. The exact click-path/menu label inside INWX's live customer dashboard, and whether subaccount DNS roles can be further restricted to specific domains, could not be independently verified — INWX's own knowledge base (kb.inwx.com) and API doc pages (inwx.com/en/help/apidoc, inwx.ch/en/help/apidoc) returned empty or 404 responses when fetched directly. **Not independently verified — confirm before publishing**: exact subaccount-creation menu path, and whether a DNS-role subaccount can be restricted to a single domain rather than all domains on the account.

---

## IONOS DNS

- **Credential-creation link**: https://developer.hosting.ionos.com/keys — log in, click "Create new key" (per IONOS's own Developer Portal "Get started" documentation at https://developer.hosting.ionos.com/docs/getstarted).
- **Minimum required permission/scope**: IONOS's Developer Portal API keys are not documented as offering a per-product (e.g. "DNS only") scope selector at creation time — the key-creation flow described in IONOS's own docs asks only for a name/label, and the resulting key works against whichever IONOS Developer APIs (Domains, DNS, SSL, Cloud/Reseller) the underlying account is entitled to use. There is no narrower "DNS-only" role to select; the credential must be treated as account-wide for API purposes.
- **Zone/project scoping guidance**: No zone/domain-level scoping is exposed for these API keys — a single key grants DNS API access to every domain/zone on the IONOS account. The only mitigation is using a separate IONOS (sub)account dedicated to the domain(s) being automated, if IONOS's account structure supports that for the customer's contract type; otherwise the key must be treated as full-account-blast-radius.
- **Field-by-field mapping**:
  - `api_key` — after clicking "Create new key" at https://developer.hosting.ionos.com/keys, IONOS displays a **prefix** and a **secret**; concatenate them as `prefix.secret` (this exact `prefix.secret` format is IONOS's own documented convention). The secret is shown only once at creation time and cannot be retrieved again afterward.
- **Credential rotation/revocation guidance**: Generate a new key from the same Developer Portal keys page and update the plugin with the new `prefix.secret` value, then delete the old key from the keys list to revoke it (IONOS's Cloud Panel API-key documentation confirms that access tied to a deleted key "will be revoked and cannot be restored"; the exact delete control on the DNS-specific Developer Portal keys page — as opposed to the separate Cloud Panel server API-key UI — was not directly loaded during this research).
- **Common errors and resolutions**:
  - 401/invalid key errors — the `prefix.secret` was likely truncated or the secret was regenerated (secrets are shown once); re-copy the full value or generate a fresh key.
  - Record changes silently fail on the wrong domain — because keys are account-wide, verify the `zone` value in the plugin config points at the intended domain rather than assuming key scope limits blast radius.
  - Key stopped working after a period — Developer Portal keys can be deleted/rotated by any account admin; if unexpected, check the keys list for whether the key was deleted or superseded.
  - DNS API returns permission errors despite a valid key — confirm the IONOS account/contract actually has the DNS API product enabled, since the key only inherits whatever APIs the account is entitled to.
- **Terms of Service / Privacy Policy**: Terms https://www.ionos.com/terms-gtc/general-terms-and-conditions/ — Privacy https://www.ionos.com/terms-gtc/privacy-policy/
- **Evidence/verification status**: The credential-creation URL (https://developer.hosting.ionos.com/keys) and the `prefix.secret` format are corroborated by IONOS's own "Get started" documentation content as surfaced via search and by a maintainer discussion on a certbot-dns-ionos plugin repo referencing `dns_ionos_prefix`/`dns_ionos_secrets`. Direct WebFetch of developer.hosting.ionos.com pages returned empty content (likely JS-rendered/SPA), and the keys page itself redirected to an IONOS OAuth login wall, so the live UI could not be directly inspected. **Not independently verified — confirm before publishing**: whether the DNS API keys page has its own delete/revoke control distinct from the Cloud Panel server API-key deactivation flow that was found instead.

---

## Joker.com DNS

- **Credential-creation link**: Log in to the Joker.com Dashboard at https://joker.com, click the **"DNS"** action next to the target domain, and turn on the **"Dynamic DNS active"** slider for that domain — Joker.com's own FAQ describes this flow and states a dialog then shows the DynDNS username and password for that domain.
- **Minimum required permission/scope**: There is no separate "role" or "scope" concept for Dynamic DNS credentials — enabling Dynamic DNS on a domain issues a single-purpose username/password pair that can only update DNS records (specifically A/AAAA-style dynamic-update records) for that domain via Joker's DynDNS Update API; it cannot perform registrar actions (transfers, nameserver changes at the registrar level, etc.), which is inherently the minimum-privilege outcome this plugin needs.
- **Zone/project scoping guidance**: Already scoped by design — Joker.com's FAQ explicitly states these DynDNS credentials "are only valid for those entries with the specific domain," i.e., they are per-domain, not account-wide. This is the best-case outcome among this batch: no broader blast radius is possible with this credential type.
- **Field-by-field mapping**:
  - `zone` — the registered domain name itself, as shown in the Joker.com Dashboard domain list.
  - `username` — the Dynamic DNS username shown in the dialog when Dynamic DNS is enabled for that domain (Dashboard > domain > DNS > enable "Dynamic DNS active").
  - `password` — the Dynamic DNS password shown in that same dialog, alongside the username.
- **Credential rotation/revocation guidance**: Joker.com's public FAQ does not document an explicit "regenerate" button for these credentials. The safe, documented path to revoke/rotate is to turn the "Dynamic DNS active" slider off and back on for the domain in the Dashboard, which the plugin's driver should treat as issuing a new credential pair; disabling it alone revokes the old pair's ability to update records. **Not independently verified — confirm before publishing**: whether toggling the slider off/on actually issues a fresh password or reuses the same one, since this was not stated explicitly in the fetched FAQ content.
- **Common errors and resolutions**:
  - Authentication fails despite correct-looking credentials — confirm the plugin is using the domain-specific DynDNS username/password, not the admin's Joker.com account login (they are documented as different credential sets, a common point of confusion).
  - Updates silently do nothing — Dynamic DNS may have been toggled off for the domain since the credentials were issued; re-check the "Dynamic DNS active" slider in the Dashboard.
  - Wrong record type / record not found — the DynDNS Update API is designed for dynamic host records, not arbitrary TXT record management; confirm the plugin's driver is using Joker's documented DynDNS update endpoint/parameters (`https://svc.joker.com/nic/update?username=...&password=...&myip=...&hostname=...`) rather than assuming general DNS-zone API semantics.
  - Multiple domains, one set of credentials — each domain gets its own DynDNS credential pair; do not attempt to reuse one domain's username/password for another domain's zone.
- **Terms of Service / Privacy Policy**: Terms https://joker.com/terms/general — Privacy https://joker.com/index.joker?mode=page&page=impressum
- **Evidence/verification status**: The enable flow, dialog behavior, and per-domain (not account-wide) scoping are directly quoted from Joker.com's own FAQ page (fetched via redirect to joker.com/faq/link/78, titled "Dynamic DNS (DynDNS)"). Credential rotation/regeneration behavior is not documented on that page and is flagged above as not independently verified.

---

## Linode DNS

- **Credential-creation link**: https://cloud.linode.com/profile/tokens — click "Create a Personal Access Token" (confirmed via Akamai's own Linode API/Cloud Manager documentation at techdocs.akamai.com/cloud-computing/docs/manage-personal-access-tokens).
- **Minimum required permission/scope**: On the token-creation form, set the **Domains** row to **Read/Write** and leave every other product/service row at **No Access**. Akamai's own docs confirm three access levels exist per product (No Access / Read Only / Read/Write), and that Read/Write on Domains is what's needed to manage DNS records via the API.
- **Zone/project scoping guidance**: A Personal Access Token cannot be limited to a single domain/zone — Linode's own community support staff (Linode/Akamai employee response in the official Linode Community Q&A) confirm "API tokens have the same permissions as the linode.com user that owns them," and that Domains Read/Write on a token applies to every domain on the account. The documented workaround for true single-zone isolation is to create a **separate limited/child user** in Cloud Manager (with "full account access" turned off) that is granted Domains Read/Write via that user's own permissions page, and generate the Personal Access Token under that restricted user rather than the primary account — this narrows the blast radius to whatever that child user can see, though Linode's user-permission model is still not natively per-domain, only per-product.
- **Field-by-field mapping**:
  - `api_token` — the Personal Access Token string shown once immediately after clicking "Create Token" on https://cloud.linode.com/profile/tokens (or under the restricted child user's own token page, per the scoping guidance above); it is not retrievable again after the creation dialog is closed.
- **Credential rotation/revocation guidance**: Go to Cloud Manager > (username) > API Tokens, find the token, and click **Revoke** to immediately invalidate it (documented on Akamai's manage-personal-access-tokens page: "Once revoked, any application using this token will no longer be authorized to access your account"). There is no in-place "rotate" action — rotation means creating a new token first, updating the plugin, then revoking the old one.
- **Common errors and resolutions**:
  - "Unauthorized"/403 on record writes — the token's Domains scope is set to Read Only or No Access; edit is not possible after creation, so a new token must be issued with Read/Write on Domains.
  - Token appears to grant more access than expected — remember tokens inherit the full permission set of the owning user; if that user has "full account access," so does every token it creates, regardless of the per-product toggles chosen at creation (the No Access/Read/Read-Write choices only apply cleanly when the owning user itself is a restricted, non-full-access user).
  - Token stops working unexpectedly — check whether it was revoked (accidentally or by another admin) or hit its configured expiry, since tokens can be created with a fixed expiry date.
  - Wrong domain updated/no domain found — because the token is account-wide, verify the exact domain name configured in the plugin's `zone`-equivalent setting rather than assuming the token itself limits which domain can be touched.
- **Terms of Service / Privacy Policy**: Terms https://www.akamai.com/legal/msa — Privacy https://www.akamai.com/legal/privacy-statement
- **Evidence/verification status**: Token creation steps, the No Access/Read Only/Read-Write scope model, and the Revoke button behavior were verified directly against Akamai's own Linode documentation (techdocs.akamai.com/cloud-computing/docs/manage-personal-access-tokens, which the original linode.com docs URL now redirects to). The "tokens cannot be scoped to a single domain, only via a restricted child user" finding is corroborated via a real Linode staff (caker) response in Linode's official Community Questions forum — a primary-source support channel, though not the formal docs page itself.

---

## Mythic Beasts

- **Credential-creation link**: https://www.mythic-beasts.com/customer/api-users — Mythic Beasts' own DNS API v2 tutorial (mythic-beasts.com/support/api/dnsv2/tutorial) directs admins here to create an API key.
- **Minimum required permission/scope**: Create the key, give it a name, and under the **"Primary DNS API v2"** permission heading click **"Add permit"** once for each zone the key should manage — this is the documented mechanism for granting the narrowest useful scope (a specific zone) rather than blanket account access.
- **Zone/project scoping guidance**: Mythic Beasts explicitly supports per-zone (and even per-record) restriction: their own tutorial states permissions "can be granted for all zones on your account, for specific zones, or for individual records." For DNS-01 use, restrict the key's DNS API v2 permit(s) to only the zone(s) being automated — this is the best zone-isolation story in this batch alongside Joker.com's inherently-scoped DynDNS credentials.
- **Field-by-field mapping**:
  - `key_id` — the Key ID shown for the API key entry created at https://www.mythic-beasts.com/customer/api-users.
  - `secret` — the Secret shown alongside that Key ID at creation time in the same control panel screen.
- **Credential rotation/revocation guidance**: Manage/delete the key from the same API Users control panel page (mythic-beasts.com/customer/api-users) that lists existing keys; deleting an entry there revokes that key_id/secret pair. **Not independently verified — confirm before publishing**: the exact delete/revoke button label and confirmation flow, since this requires an authenticated Mythic Beasts control-panel session that could not be inspected directly — Mythic Beasts' own public support pages describe key *creation* and *permission* granularly but do not document the deletion UI in the pages that could be fetched.
- **Common errors and resolutions**:
  - "Permission denied" creating/deleting TXT records — the key has no "Add permit" entry for the target zone under Primary DNS API v2, or the permit was scoped to individual records rather than the whole zone; add a zone-level permit.
  - Works for one zone but not another — permits are additive per zone; a key restricted to zone A will not touch zone B until a separate permit is added for zone B.
  - Key appears valid but all calls fail — confirm the key was granted a permit under **Primary DNS API v2** specifically (as opposed to, e.g., the separate Domains API or Secondary DNS API products Mythic Beasts also exposes, which use different permission grants).
  - Unexpectedly broad access — a key created without narrowing "Add permit" to specific zones defaults to (or can be left at) all-zones access; audit existing keys' permit lists if least-privilege is required.
- **Terms of Service / Privacy Policy**: Terms https://www.mythic-beasts.com/terms/overview — Privacy https://www.mythic-beasts.com/terms/privacy
- **Evidence/verification status**: Credential-creation URL, the "Add permit" per-zone mechanism, and the all-zones/specific-zones/individual-records granularity are directly quoted from Mythic Beasts' own DNS API v2 tutorial page. Key deletion/revocation UI specifics could not be confirmed from public pages (requires login) and are flagged above.

---

## Namecheap DNS

- **Credential-creation link**: Log in to Namecheap, go to **Profile > Tools**, scroll to **Business & Dev Tools**, and click **MANAGE** next to **Namecheap API Access** — confirmed directly from Namecheap's own API FAQ knowledge-base article.
- **Minimum required permission/scope**: Namecheap's API has no granular permission/role model — enabling "Namecheap API Access" grants that API user access to the full Namecheap API method set (domain and DNS management, and more) for the account; there is no separate "DNS-only" toggle. The practical minimum-privilege control Namecheap offers instead is the **IP whitelist** (below), not a permission scope.
- **Zone/project scoping guidance**: Access is account-wide, not scoped to a single domain — any whitelisted caller with the API key can manage DNS (and other API-exposed settings) for every domain on the account. Namecheap's own FAQ does not document a way to restrict a key to one domain; the only mitigation Namecheap exposes is restricting *which servers* can call the API at all, via IP whitelisting, not *which domains* a given call can touch.
- **Field-by-field mapping**:
  - `api_user` — by default the same as the Namecheap account username used to log in (Namecheap's FAQ notes this is the standard case; a true API "ApiUser" only differs from the account username in reseller/multi-user setups).
  - `api_key` — generated automatically and shown immediately after enabling API access at Profile > Tools > Business & Dev Tools > Namecheap API Access > MANAGE.
  - `client_ip` — the public IPv4 address of the server making the DNS-01 API calls; this exact address must be added under **Whitelisted IPs > Add IP** on the same API Access management screen before calls will succeed. Only IPv4 is supported (no IPv6), and Namecheap allows up to 10 whitelisted IPs.
- **Credential rotation/revocation guidance**: On the same management screen, click **Reset** next to the API key and confirm with the account password to generate a new key (Namecheap's FAQ/support content warns this immediately breaks any integration still using the old key). To fully revoke API access, toggle **Namecheap API Access** off on the same screen; toggling it back on later re-enables access (Namecheap's docs do not state whether this reuses or reissues the key — treat a fresh Reset as the reliable rotation path).
- **Common errors and resolutions**:
  - `Invalid request IP` / calls rejected outright — the calling server's IP is not on the Whitelisted IPs list, or (very commonly) the server's IP changed because it's on a dynamic/cloud-recycled address; add/update the whitelist entry with the current public IPv4, and note this is a recurring maintenance burden on hosts without a static IP (a widely reported pain point for exactly this DDNS/automation use case).
  - API access enabled but every call still fails — Namecheap also gates API access behind account-activity thresholds (a minimum number of registered domains, account balance, or historical spend); confirm the account still meets Namecheap's eligibility criteria if access was recently disabled unexpectedly.
  - Wrong domain updated — because access is account-wide, double-check the domain/zone parameter passed by the plugin rather than assuming the API key limits which domain can be modified.
  - Key rotated but plugin still fails — after clicking Reset, the old key stops working immediately; make sure the new key was saved into the plugin's configuration, not just noted elsewhere.
- **Terms of Service / Privacy Policy**: Terms https://www.namecheap.com/legal/universal/universal-tos/ — Privacy https://www.namecheap.com/legal/general/privacy-policy/
- **Evidence/verification status**: The enable/manage path, IP-whitelist requirement (IPv4-only, up to 10 IPs), and Reset/toggle-off rotation mechanism were verified directly against Namecheap's own API FAQ knowledge-base article (namecheap.com/support/knowledgebase/article.aspx/9739/63/api-faq). The lack of any per-domain or per-permission scoping is corroborated by the absence of any such control across all of Namecheap's own API documentation surfaced in this research (no scope/role field is described anywhere), rather than an explicit "there is no such feature" statement from Namecheap.

---

## Name.com DNS

- **Credential-creation link**: Log in to Name.com, click the **User icon (top right) > Settings**, open **API Tokens** under the **Security** section, click **Create API Token**, accept the API Access Agreement, name the token, and click **Generate new token** — confirmed directly from Name.com's own Knowledge Base article "Signing up for API access."
- **Minimum required permission/scope**: Name.com's API token system does not expose a granular permission/role picker at token creation — a generated token authenticates as the account and can call any Core API method the account has access to (domain and DNS management included). There is no documented "DNS-only" or read-vs-write role to select.
- **Zone/project scoping guidance**: Tokens are account-wide, not scoped to a single domain — Name.com's own docs describe one Production token and one Development/Test token per account (plus the ability to generate additional named tokens), with no per-domain restriction option documented anywhere in their API overview or getting-started pages. The only exposed narrowing control is IP-based: Name.com's knowledge base mentions the option to "limit API access to certain IPs" by whitelisting them, which reduces where a token can be used from, not which domains it can touch.
- **Field-by-field mapping**:
  - `username` — the Name.com account username used to log in (shown in Account Settings; used together with the API token for HTTP Basic Auth against the API).
  - `api_token` — the token string shown once after clicking **Generate new token** under Settings > Security > API Tokens; use the **Production** token (listed at the top of that page) for live certificate issuance against `api.name.com`, not the Development/Test token (which targets `api.dev.name.com`).
- **Credential rotation/revocation guidance**: Name.com's own public documentation does not describe a revoke/delete control for existing tokens in the pages available for this research; the documented action is generating additional named tokens from the same API Tokens screen. **Not independently verified — confirm before publishing**: whether an existing token can be individually revoked/deleted from the Settings > Security > API Tokens screen, versus only superseded by generating a new one.
- **Common errors and resolutions**:
  - Auth fails with correct-looking username/token — confirm the **Production** token is being used against `api.name.com`; a Development/Test token only works against `api.dev.name.com` and will fail (or operate on a sandboxed dataset) against the production endpoint.
  - 2FA-enabled accounts lose API access — Name.com's docs note that when account 2FA is enabled, API access must be separately toggled on under Account Settings > Security ("Name.com API Access"); if this toggle is off, API calls fail even with a valid token.
  - Calls rejected from the automation server — if IP restriction has been configured on the token/account, the DNS-01 server's public IP must be added to that allow-list.
  - Wrong domain modified — because tokens are account-wide, verify the domain parameter passed by the plugin rather than assuming the token itself limits scope.
- **Terms of Service / Privacy Policy**: Terms https://www.name.com/policies/registration-agreement — Privacy https://www.name.com/privacy-policy
- **Evidence/verification status**: The token-creation click-path, Production-vs-Development/Test token distinction, and the "toggle Name.com API Access on under Security when 2FA is enabled" requirement were verified directly against Name.com's own Knowledge Base article. The absence of per-domain scoping is corroborated by its absence across all of Name.com's own API overview/getting-started documentation reviewed, rather than an explicit negative statement from Name.com. **Not independently verified — confirm before publishing**: token revocation/deletion mechanism.

---


## NameSilo DNS

- **Credential-creation link**: https://www.namesilo.com/account/api-manager (the "API Manager" page, linked from NameSilo's own support article at https://www.namesilo.com/support/v2/articles/account-options/api-manager)
- **Minimum required permission/scope**: NameSilo does not offer operation-level or record-type permission scoping on its API key. A single API key is generated per account and, once active, has full access to every NameSilo API call the account is entitled to (domain management, DNS record management, account operations) — there is no "DNS only" or "read-only" role to select. The only narrowing control NameSilo exposes is IP allow-listing (see below), not a permission/capability scope.
- **Zone/project scoping guidance**: The API key cannot be scoped to a single domain/zone — it is account-wide by design. The available mitigation is the API Manager's IP restriction field: an admin can enter up to 5 IP addresses to limit which originating hosts may use the key; if left blank, the key accepts requests from any IP. Administrators running this plugin from a fixed egress IP (e.g., a static hosting IP or NAT gateway) should populate this field to reduce blast radius, since domain-level scoping is not available.
- **Field-by-field mapping**:
  - `api_key` (API Key): Generated on the API Manager page (https://www.namesilo.com/account/api-manager) — first-time users must explicitly generate a new key; NameSilo displays the key once in the account UI (unlike many providers, NameSilo does not claim it is shown only once and unrecoverable in the same way — it is viewable again in the API Manager UI after generation, but see rotation note below for what happens if you "regenerate").
- **Credential rotation/revocation guidance**: NameSilo has no dedicated "revoke" action separate from replacement. To rotate, generate a new API key from the API Manager page, which invalidates the previous key; if a key is lost/forgotten there is no retrieval option — generating a new key is the only remedy. Update the plugin's stored `api_key` immediately after rotating, since the old value stops working the moment a new key is generated.
- **Common errors and resolutions**:
  - **"Invalid API key" / authentication failure**: Usually means the key was rotated (regenerated) after being copied into the plugin, or it was copied with leading/trailing whitespace. Re-copy the current key from the API Manager page.
  - **Requests silently rejected / timing out**: If an IP allow-list was configured in the API Manager and the server running this plugin's outbound requests is not in that list (or its IP changed, e.g., after a hosting migration), NameSilo will reject the call. Clear or update the IP list.
  - **DNS-01 TXT record not propagating / API call succeeds but validation fails**: Confirm the domain is actually using NameSilo's own nameservers rather than an external DNS provider — NameSilo's DNS API only manages zones hosted on NameSilo's own nameservers.
- **Terms of Service / Privacy Policy**: Terms https://www.namesilo.com/support/v2/articles/general-terms/terms-and-conditions — Privacy https://www.namesilo.com/support/v2/articles/general-terms/privacy-policy
- **Evidence/verification status**: Verified live/via official docs: credential-creation URL, key-is-account-wide (no scoping), IP allow-list feature and its "up to 5 IPs" limit, no-retrieval-after-loss rotation behavior, legal links (given, not re-checked). Corroborated via search only (official page did not state explicitly): the exact click path from the main account dashboard to the API Manager page — "Not independently verified — confirm before publishing" for the precise dashboard menu label sequence (the URL itself is verified and can be used directly).

---

## netcup DNS

- **Credential-creation link**: netcup Customer Control Panel (CCP) at https://www.customercontrolpanel.de/ — API key and legacy API password are both generated under **Master Data → API** once logged in. Reference: https://www.netcup.com/en/helpcenter/documentation/domain/our-api
- **Minimum required permission/scope**: netcup's CCP API key/password model has no granular permission system — there is no "DNS-only" role or capability checkbox. Any valid API key + API password pair combined with the customer number authenticates as the full account via netcup's SOAP/REST CCP API, which includes DNS zone management alongside server/domain/billing operations. There is no narrower scope available for this credential type.
- **Zone/project scoping guidance**: Not possible to scope to a single domain/zone — the credential is account-wide by construction, and netcup does not offer per-zone API keys. Treat this credential as equivalent in sensitivity to full CCP access and restrict who can view it in the plugin's admin UI accordingly.
- **Field-by-field mapping**:
  - `customer_number` (Customer number): Displayed next to the account name at the top of the CCP after login; it is also the username used to log into the CCP itself (sent to the admin by email at signup).
  - `api_key` (API key): CCP → **Master Data → API** → "API Keys" section → click "Create API Key" (agree to the API terms of use, add a description, confirm). The key is shown after creation.
  - `api_password` (API password): Same **Master Data → API** page, "Legacy API Keys" section → "Generate/Regenerate API Password" → confirm. The password is displayed exactly once and cannot be retrieved again; generating a new one immediately invalidates the previous one (netcup allows only one active API password per account at a time).
- **Credential rotation/revocation guidance**: The API key can be deleted individually from the same Master Data → API page (delete/trash action next to the key entry). The API password has no independent delete — "rotating" it means generating a new one, which immediately deactivates the prior password (there is only ever one live password per account).
- **Common errors and resolutions**:
  - **Login/authentication errors on API calls**: Confirm all three values (customer number, API key, API password) are current — regenerating the API password invalidates the old one immediately, so a stale saved password is a common cause.
  - **"Customer number" confused with a different netcup identifier**: netcup's newer Server Control Panel (SCP) uses a separate Keycloak-based login where the "username" shown can be trivially confused with the CCP customer number; for this plugin's DNS API field, use the CCP customer number specifically (the number shown at the top of the CCP dashboard), not any SCP/Keycloak identifier.
  - **DNS changes not applying**: Verify the domain's DNS is actually managed through netcup's own DNS (Domains → DNS) rather than delegated to external nameservers, since the CCP DNS API only manages zones netcup itself is authoritative for.
- **Terms of Service / Privacy Policy**: Terms https://www.netcup.com/en/terms-and-conditions — Privacy https://www.netcup.com/en/contact/data-privacy
- **Evidence/verification status**: Verified via official netcup documentation: Master Data → API menu path, API key creation flow, API password generation/one-time-display/single-password behavior, lack of scoping, key deletion mechanism. Corroborated via community/forum sources rather than an official netcup page found directly: the precise statement that the customer number is "displayed next to your name at the top of the CCP" — treat as reasonably reliable but "Not independently verified — confirm before publishing" against a live CCP screenshot before publishing.

---

## Netlify DNS

- **Credential-creation link**: https://app.netlify.com/ → avatar (top right) → **User settings** → **Applications** → **Personal access tokens** → **New access token**. Reference: https://docs.netlify.com/api-and-cli-guides/api-guides/get-started-with-api/
- **Minimum required permission/scope**: Netlify personal access tokens (PATs) are not scoped by capability — a PAT carries the full permissions of the user account that created it (i.e., whatever that user can do in the Netlify UI, the token can do via the API, including DNS zone/record management if the user has access to the relevant team's DNS zone). There is no "DNS records only" or read-only option when generating a PAT. Because of this, the practical minimum-privilege step is to generate the token from a Netlify user account that itself has the least access necessary (e.g., a dedicated automation user added only to the team/site that owns the DNS zone), rather than scoping the token itself.
- **Zone/project scoping guidance**: The token cannot be restricted to a single DNS zone or site — it is tied to the creating user's account-wide (or team-wide, if the user belongs to multiple teams) permissions. If the Netlify account manages multiple sites/teams beyond the one needing DNS-01 automation, consider a separate, minimally-privileged Netlify user dedicated to issuing this token, since the token itself offers no zone restriction.
- **Field-by-field mapping**:
  - `api_token` (Personal access token): Generated at User settings → Applications → Personal access tokens → New access token — give it a descriptive name, optionally set an expiration date, click "Generate token," and copy it immediately (it is shown once).
- **Credential rotation/revocation guidance**: Official Netlify docs describe generation and note that resetting the Netlify account password immediately invalidates all personal access tokens and OAuth tokens issued before the reset (a blunt but effective revocation-of-all-tokens mechanism). Individual token deletion/revocation is expected to be available from the same Applications → Personal access tokens list in the Netlify UI (a "revoke"/delete control next to each listed token), but the specific per-token revoke control was not directly confirmed against a live page during this research pass — see verification status.
- **Common errors and resolutions**:
  - **401/403 on API calls**: Token was invalidated by a password reset (all prior tokens are invalidated on reset) — generate a new token and update the plugin.
  - **Token works for some sites but the DNS zone can't be managed**: The creating user account does not have access to the team that owns the DNS zone; add that user to the correct team (or generate the token from a user who already has that access) rather than assuming the token itself can be re-scoped.
  - **Expired token**: PATs can be given an expiration date at creation; if API calls start failing after previously working, check whether the chosen expiration date has passed and generate a replacement.
- **Terms of Service / Privacy Policy**: Terms https://www.netlify.com/legal/terms-of-use/ — Privacy https://www.netlify.com/privacy/
- **Evidence/verification status**: Verified via official Netlify docs: menu path, token generation steps, one-time display, expiration option, password-reset-invalidates-all-tokens behavior, and lack of granular scoping. **Not independently verified — confirm before publishing**: the exact UI control/label for revoking a single token without a full password reset (documentation describes creation and the password-reset side effect but this pass did not locate an explicit "how to delete one token" doc page).

---

## Njalla

- **Credential-creation link**: https://njal.la/settings/api/ (Njalla account → Settings → API). Note: this domain could not be directly fetched during this research pass (see verification status) — this URL is corroborated by multiple independent third-party integration docs (certbot plugin, Python/Go/Node client libraries) that all point to the same settings path, not by a direct fetch of njal.la itself.
- **Minimum required permission/scope**: Njalla's API token supports scoping at creation time via parameters including `allowed_domains`, `allowed_servers`, `allowed_methods`, `allowed_types`, and an `acme` flag. The narrowest workable scope for DNS-01 is to restrict `allowed_methods` to only the DNS-editing calls actually needed (commonly documented by third-party ACME integrations as `get-domain`, `list-records`, `add-record`, and `remove-record`), restrict `allowed_types` to `TXT`, and — where the integration supports it — restrict by record-name prefix to `_acme-challenge`. Njalla does not appear to expose a separate named "role" (like "read-only" or "DNS admin") the way some providers do; scoping is done through these explicit allow-lists on the token itself.
- **Zone/project scoping guidance**: Yes — unlike several providers in this batch, Njalla tokens can be scoped to specific domains via `allowed_domains` at token-creation time. An admin managing only one domain through this plugin should list only that domain when creating the token, meaningfully reducing blast radius compared to an all-domains token. If a static outbound IP is available, Njalla also supports restricting the token to that IP address, which the plugin's operating environment should also apply if the "from"/IP-restriction option is present.
- **Field-by-field mapping**:
  - `api_token` (API token): Generated at njal.la → Settings → API (https://njal.la/settings/api/), scoping domains/methods/record-types/IP as above at creation time.
- **Credential rotation/revocation guidance**: Not independently verified — confirm before publishing. This research pass could not directly load njal.la's settings pages (the domain was unreachable to the fetch tooling used), and no third-party source consulted described the specific UI control for deleting or rotating an existing token. Treat "generate a new, more narrowly scoped token and delete the old one from the API settings list" as the expected pattern pending direct confirmation against the live UI.
- **Common errors and resolutions**:
  - **API calls rejected for a domain that should be allowed**: If the token was created with `allowed_domains` restricted to specific domains, adding a new domain to this plugin later requires either widening that token's allow-list or issuing a new token that includes the new domain — the token does not automatically cover domains added to the account afterward.
  - **Method-restricted token fails on TXT record deletion**: If `allowed_methods` was scoped too narrowly (e.g., only `add-record` was allowed), Let's Encrypt's post-validation cleanup step (removing the `_acme-challenge` TXT record) will fail. Ensure `remove-record` (or equivalent) is included, not just record creation.
  - **Token stops working from a new server**: If the token was IP-restricted to a specific address and the plugin's outbound IP changed (e.g., after a hosting migration), authentication will fail; update the IP restriction or reissue the token.
- **Terms of Service / Privacy Policy**: Terms https://njal.la/tos/ (no separate Privacy Policy exists — njal.la/privacy/ 404s, and Njalla is deliberately structured without a published operating-entity name for registrant anonymity).
- **Evidence/verification status**: Corroborated via secondary sources only (a certbot-dns-njalla plugin's own documentation, and independent third-party Python/Go/Node Njalla API client libraries), not via a direct fetch of njal.la's own pages — the njal.la domain was unreachable to the web-fetch tooling used in this research pass for every URL attempted (settings page, API docs page, and the ToS page itself), which is consistent with the ground-truth brief's note that njal.la is a hard target to verify directly. Credential-creation link, scoping parameters, and common errors are corroborated, not independently verified live. Rotation/revocation guidance is explicitly unverified — see above.

---

## NS1

- **Credential-creation link**: https://my.nsone.net/ — this is confirmed to be the live, active IBM NS1 Connect customer portal (distinct from the ns1.com marketing site, which now redirects site-wide into IBM's site). Once logged in: click your username (upper right) → **Account Settings** → **Users & Teams** → **API Keys** tab → **Add Key**. Official doc: https://www.ibm.com/docs/en/ns1-connect?topic=keys-create-api-key
- **Minimum required permission/scope**: NS1/IBM NS1 Connect API keys support fine-grained permission flags. For DNS-01 TXT record management, the narrowest applicable permissions are the "DNS Permissions" group on the key: enable `dns_manage_zones` (allows creating/editing/deleting records within allowed zones) while leaving every other permission category (account management, billing, monitoring, data feeds, security/2FA, redirects, insights) disabled. Do not enable broader account-management permissions such as `account_manage_apikeys`, `account_manage_users`, or `account_manage_teams`.
- **Zone/project scoping guidance**: Yes — NS1 keys support zone-level restriction at creation time. Set `dns_zones_allow_by_default` to false and add only the specific zone(s) this plugin manages to the key's zone allow-list ("Specific zones" in the portal UI), rather than leaving the key able to reach every zone on the account. This scoping is fixed at key creation — the zone list cannot later be widened by editing zone DNS elsewhere without also updating the key.
- **Field-by-field mapping**:
  - `api_key` (API key): Generated at my.nsone.net → username menu → Account Settings → Users & Teams → API Keys tab → Add Key, with `dns_manage_zones` enabled and the zone allow-list restricted to the relevant zone(s) as above. The key is used in the `X-NSONE-Key` HTTP header for API requests.
- **Credential rotation/revocation guidance**: IBM's NS1 Connect documentation states API keys support secret expiration and rotation. Rotate/revoke from the same Account Settings → Users & Teams → API Keys tab in the portal — edit or delete the key entry. Exact button labels for "rotate" vs. "delete" were not confirmed against a live screenshot in this research pass (see verification status).
- **Common errors and resolutions**:
  - **401/403 on API calls**: Confirm the key still has `dns_manage_zones` enabled and that the target zone is present in the key's zone allow-list — NS1 keys fail closed for zones not explicitly allowed once `dns_zones_allow_by_default` is false.
  - **Key works in the portal test but not from the plugin's server**: Check for an IP allow-list configured on the key (NS1 supports `ip_whitelists`/`ip_whitelist_strict` restrictions) that may not include the plugin's outbound IP.
  - **Zone visible in the portal but not reachable via the API key**: A zone added to the account after the key was created will not automatically be covered by a zone-restricted key — the zone allow-list must be edited to add it.
  - **Confusion about where to log in**: Because ns1.com now redirects into IBM's marketing site, admins sometimes look for credential management there; the actual portal remains my.nsone.net, not an IBM Cloud console URL.
- **Terms of Service / Privacy Policy**: Terms https://www.ibm.com/legal/terms — Privacy https://www.ibm.com/us-en/privacy (IBM's general policies; NS1-specific, but IBM's Privacy Statement body text explicitly references NS1)
- **Evidence/verification status**: Verified via official IBM NS1 Connect documentation and portal confirmation: my.nsone.net is live/active in 2026, menu path to API Keys, existence of granular `dns_*` permission flags and zone-restriction ("Specific zones") capability, `X-NSONE-Key` header usage, existence of key expiration/rotation support. Corroborated via a third-party schema reference (Pulumi's NS1 provider docs, which mirror NS1's own API key schema) for the complete list of granular permission field names (`dns_manage_zones`, `dns_zones_allow_by_default`, `dns_zones_allows`, `dns_records_allows`, etc.) — the field names are consistent across sources but were not confirmed against a live "Add Key" form screenshot. **Not independently verified — confirm before publishing**: the exact button/label used to rotate vs. permanently delete a key in the current portal UI.

---

## OVH DNS

- **Credential-creation link**: Endpoint-specific application registration pages: **ovh-eu** → https://eu.api.ovh.com/createApp/ (redirects to an OVHcloud-hosted app-registration form) — **ovh-ca** → https://ca.api.ovh.com/createApp/ — **ovh-us** → https://api.us.ovhcloud.com/createApp/. General reference: https://docs.ovhcloud.com/en/guides/manage-and-operate/api/first-steps
- **Minimum required permission/scope**: OVH uses a distinctive three-part credential model rather than a single scoped key:
  1. **Application key + application secret** — created once per "application" (integration) via the createApp page above; these identify the application itself and are not, on their own, tied to any specific account access rights.
  2. **Consumer key** — a second credential that must be explicitly authorized against a specific OVH customer account, and which carries its own **access rules**: a list of (HTTP method, API route pattern) pairs it is permitted to call. This is where real scoping happens. For DNS-01 automation, the narrowest documented working rule set restricts the consumer key to the DNS zone routes only, for example (values used verbatim from OVH/community/ACME-client documentation): `GET /domain/zone/*`, `POST /domain/zone/*`, `PUT /domain/zone/*`, `DELETE /domain/zone/*/record/*` (some references scope the DELETE more narrowly to `/domain/zone/*/record/*` specifically, and GET/POST/PUT to `/domain/zone/*` and its record sub-routes, e.g. `/domain/zone/*/record` and `/domain/zone/*/record/*`). There is no single named "role" like "DNS admin" — the admin (or the automation flow) constructs this route allow-list directly.
  - The simplest path to generate all three credentials together with a chosen rule set in one step is OVH's **createToken** endpoint, which accepts the access rules as query parameters, e.g.: `https://eu.api.ovh.com/createToken/?GET=/domain/zone/*&POST=/domain/zone/*&PUT=/domain/zone/*&DELETE=/domain/zone/*/record/*` (substitute `ca.api.ovh.com` or `api.us.ovhcloud.com` for the other two regions). This single flow both registers an application and issues an authorized, pre-scoped consumer key together — useful when this plugin's own form doesn't need to walk the admin through creating an "application" separately.
- **Zone/project scoping guidance**: The consumer key's access rules can be scoped down to a specific zone (e.g., `GET /domain/zone/example.com/*` instead of the wildcard `/domain/zone/*`) if only one domain will ever use DNS-01 through this plugin, which is materially narrower than most other providers in this batch. If multiple domains under the same OVH account will use the plugin, the wildcard zone pattern is the pragmatic choice, trading some scope for not needing to reissue the consumer key every time a new domain is added.
- **Field-by-field mapping**:
  - `endpoint` (Endpoint: ovh-eu, ovh-ca, or ovh-us, placeholder `ovh-eu`): Determined by which OVH accountis in use — OVH's European accounts use `ovh-eu`, OVH's North-American/Canadian accounts use `ovh-ca`, and OVH US accounts use `ovh-us`. This corresponds directly to which regional createApp/createToken host the admin used (eu.api.ovh.com / ca.api.ovh.com / api.us.ovhcloud.com) — use the same region consistently across all three credential values below.
  - `application_key`: Obtained from the createApp step (or from the combined createToken flow) on the region-matched host above; shown as "Application Key (AK)" after submitting the app-registration form.
  - `application_secret`: Obtained in the same createApp/createToken step, shown as "Application Secret (AS)" — must be kept confidential, never disclosed alongside the application key in shared contexts.
  - `consumer_key`: Obtained by authorizing a consumer key against the application (either via the createToken combined flow, or via a separate authorization step using AddRules-style access rules against `/domain/zone/*`), then completing the validation URL OVH returns (which requires logging into the OVH account to approve the requested access rules).
- **Credential rotation/revocation guidance**: From the OVHcloud Control Panel, applications and their consumer keys can be managed and deleted under the API keys management page (reachable via Identity, Security & Operations in the panel's menu structure) — a "delete" action is available per listed application/key. Programmatically, `DELETE /me/api/application/{applicationId}` revokes an application (and its associated consumer key) via the API itself. There is no separate "rotate in place" — revoke the old application/consumer key and run the createApp/createToken flow again for a replacement.
- **Common errors and resolutions**:
  - **"Invalid Application key" / "Invalid consumer key" errors**: Confirm the `endpoint` field matches the region the application/consumer key were actually created against — an ovh-eu consumer key will not authenticate against the ovh-ca or ovh-us API hosts, and vice versa.
  - **403/"insufficient rights" on writing a TXT record**: The consumer key's access rules were scoped too narrowly (e.g., only GET was authorized, or the route pattern didn't include the record sub-path). Reissue the consumer key with `POST`/`PUT`/`DELETE` included on `/domain/zone/*` (or the record-specific sub-routes) for the zone(s) in use.
  - **Consumer key authorization never completes**: OVH's authorization flow requires the account holder to visit a validation URL and approve the request while logged in; if that step is skipped, the consumer key remains unvalidated and all API calls using it will fail.
  - **Works for one domain but not a newly added one**: If the consumer key's access rules were scoped to a specific zone (e.g., `/domain/zone/example.com/*`) rather than the wildcard, a newly added domain will not be covered — either widen the rule or issue a new consumer key.
- **Terms of Service / Privacy Policy**: Terms https://www.ovhcloud.com/en/terms-and-conditions/ — Privacy https://www.ovhcloud.com/en/terms-and-conditions/privacy-policy/
- **Evidence/verification status**: Verified via official OVH GitHub client documentation (go-ovh) and OVH/community support documentation: the createApp/createToken flow, the three-part credential model, access-rule (route + HTTP method) based scoping, the three regional endpoints and their createApp/createToken hosts, and application/consumer-key revocation via both the Control Panel and the `DELETE /me/api/application/{applicationId}` API call. Corroborated via long-standing third-party ACME/DNS-integration documentation (acme.sh wiki, external-dns, DNSControl) for the specific `/domain/zone/*` example query strings used above — these are widely reused across independent integrations and internally consistent, but were not re-verified against OVH's own createToken form UI directly in this pass.

---

## Porkbun DNS

- **Credential-creation link**: https://porkbun.com/account/api (reachable from the Porkbun dashboard via **ACCOUNT** (top right) → **API Access**). Reference: https://kb.porkbun.com/article/190-getting-started-with-the-porkbun-api
- **Minimum required permission/scope**: Porkbun's API key model is all-or-nothing — there is no capability-level permission system (no "DNS only" vs. "domain transfer" distinction on the key itself). Once an API key/secret pair is created, the actual scoping mechanism is per-domain: API access must be separately toggled on for each domain the key is allowed to touch, under that domain's own "Details" page ("API Access" option). A key with API access enabled only for the specific domain(s) this plugin manages, and left disabled for every other domain on the account, is the narrowest configuration Porkbun supports.
- **Zone/project scoping guidance**: The key/secret pair is account-wide by nature (there is only one API key per account, not one per domain), but the per-domain "API Access" toggle described above effectively limits which zones that key can act on. Administrators should confirm this toggle is enabled only for the domain(s) actually used by this plugin's DNS-01 flow, and leave it off for unrelated domains in the same Porkbun account.
- **Field-by-field mapping**:
  - `api_key` (API Key, placeholder `pk1_...`): Generated at ACCOUNT → API Access → name the key → "Create API Key"; shown in the success message along with the secret key.
  - `api_secret` (Secret API Key, placeholder `sk1_...`): Shown in the same one-time success message as the API key immediately after creation — Porkbun explicitly warns not to navigate away before copying it, since it cannot be retrieved again afterward.
- **Credential rotation/revocation guidance**: Porkbun's own documentation confirms that if the secret key is lost before being copied, "you'll have to delete the key and generate a new one," implying a delete control exists on the API Access page; the precise UI control/label for deleting an existing (successfully saved) key was not directly confirmed against a live screenshot in this research pass. Until confirmed, treat "delete the existing key from ACCOUNT → API Access, then create a new one" as the expected rotation pattern.
- **Common errors and resolutions**:
  - **API calls rejected even with a correct key/secret**: The most common cause is that per-domain API access was never enabled for the target domain — check ACCOUNT → Domain Management → (domain) → Details → API Access and confirm it is toggled on.
  - **"Invalid API key" after previously working**: Porkbun migrated its API hostname from `porkbun.com` to `api.porkbun.com`; confirm the plugin (or any custom endpoint override) targets the current API host rather than a stale legacy one.
  - **Intermittent 503s / rate-limit failures during bulk record changes**: Porkbun enforces comparatively strict API rate limits; retry with backoff rather than assuming the credentials themselves are invalid.
  - **Secret key lost before it could be copied**: There is no recovery — delete the incomplete key and generate a fresh API key/secret pair.
- **Terms of Service / Privacy Policy**: Terms https://porkbun.com/legal/agreement/product_terms_of_service — Privacy https://porkbun.com/legal/agreement/privacy_policy
- **Evidence/verification status**: Verified via official Porkbun Knowledge Base: credential-creation menu path, one-time secret display, per-domain API Access toggle as the true scoping mechanism, and the porkbun.com → api.porkbun.com hostname migration. Corroborated via third-party client/library documentation (acme.sh, Terraform provider issue tracker) for the rate-limiting behavior. **Not independently verified — confirm before publishing**: the exact control/label for deleting an already-saved (not just an incomplete/unsaved) API key from the ACCOUNT → API Access page.

---


## PowerDNS

- **Credential-creation link**: N/A, self-hosted. PowerDNS Authoritative Server is open-source DNS server software (https://www.powerdns.com/) that the administrator already runs themselves — there is no third-party account, signup page, or vendor to visit. The credential (`api-key`) is a value the administrator invents and writes into their own server's config file.
- **Minimum required permission/scope**: There is no granular permission model in the Authoritative Server's built-in HTTP API. A single static `api-key` grants full read/write access to every zone hosted by that `pdns_server` instance — there is no "TXT-only" or per-zone scope option in the API key itself (confirmed by direct reading of PowerDNS's own HTTP API documentation, which describes only one static key sent via the `X-API-Key` header).
- **Zone/project scoping guidance**: Not possible at the API-key level — the key is all-or-nothing for the whole server. If isolation from other zones is wanted, the only real mitigation is infrastructure-level: run a dedicated `pdns_server` instance/container that hosts only the zone used for ACME validation, and/or restrict which hosts can reach the API at all via `webserver-allow-from` and firewall rules.
- **Field-by-field mapping**:
  - `api_url`: the address/port the built-in webserver binds to, set via the `webserver-address` and `webserver-port` directives in `pdns.conf` (default `webserver-address=127.0.0.1`) — e.g. `http://127.0.0.1:8081` if left at defaults, or the actual reachable host:port if the plugin runs on a different machine.
  - `api_key`: the value the administrator sets themselves via the `api-key` directive in `pdns.conf`. The API must also be turned on with `api=yes` and `webserver=yes` for the key to have any effect.
  - `server_id`: virtually always `localhost` — this is PowerDNS's default internal server name, visible in the API path pattern `/api/v1/servers/localhost`. It only differs from `localhost` in unusual multi-instance/proxy setups.
- **Credential rotation/revocation guidance**: Edit `pdns.conf`, change the `api-key` value to a new random string, then restart or reload `pdns_server` for the new key to take effect. There is no API-driven or dashboard-driven rotation mechanism — it is a plain config value, so the old key stops working the moment the new config is loaded.
- **Common errors and resolutions**:
  - `401 Unauthorized` — the `X-API-Key` value doesn't match `api-key` in `pdns.conf`, or `api=yes` was never set. Recheck both.
  - Connection refused / timeout — `webserver=yes` is missing, or `api_url` points at a host/port the webserver isn't actually bound to (check `webserver-address`/`webserver-port`), or a firewall/`webserver-allow-from` rule is blocking the plugin's IP.
  - `404` on the `server_id` path — the configured server name isn't actually `localhost` in this unusual setup; confirm with `pdns_control` or the running config.
  - Record changes silently don't apply — the zone is configured as a secondary/slave zone on this server rather than Master/Native, and slave zones reject API writes; check the zone's kind in PowerDNS.
- **Terms of Service / Privacy Policy**: Not applicable — self-hosted infrastructure.
- **Evidence/verification status**: Verified live against PowerDNS's own official documentation at https://docs.powerdns.com/authoritative/http-api/index.html for the `api`/`api-key`/`webserver`/`webserver-address`/`webserver-port` directives, the `X-API-Key` header, and the `server_id=localhost` convention. The "no rotation mechanism" and "no scoping" statements are direct readings of that same page (it describes no such features), not guesses — but if the installed PowerDNS version is old or non-standard, confirm the directive names still match before publishing.

Sources: [Built-in Webserver and HTTP API — PowerDNS Authoritative Server documentation](https://docs.powerdns.com/authoritative/http-api/index.html)

---

## RFC 2136 (TSIG dynamic DNS updates)

- **Credential-creation link**: N/A, self-hosted. RFC 2136 is a DNS protocol, not a vendor — the "provider" is whatever authoritative DNS server software the administrator already runs and controls (commonly BIND9, but also PowerDNS, Knot DNS, etc.). The TSIG key is generated locally with that server's own tooling, not obtained from any signup page.
- **Minimum required permission/scope**: There is no vendor role name to cite. The least-privilege equivalent is: authorize the TSIG key to update only the specific zone in question (via that zone's `allow-update` or `update-policy` statement in `named.conf`), rather than granting it any server-wide update permission. ISC's own BIND 9 documentation states plainly that `allow-update` should list only TSIG key names (never IP addresses or network prefixes), and separately notes that the more flexible `update-policy` statement exists for restricting a key to specific record names/types (e.g. TXT records under `_acme-challenge` only) — but the exact `update-policy` grant syntax for that finer restriction was not independently confirmed in this pass. **Not independently verified — confirm before publishing** for the precise `update-policy` grant syntax; the simpler zone-scoped `allow-update { key "keyname"; };` form is confirmed.
- **Zone/project scoping guidance**: TSIG keys are scoped per zone by construction — each zone's own `zone { ... };` block in `named.conf` decides which key names it trusts via that zone's `allow-update`/`update-policy` line, so a key used only in one zone's block has no effect on any other zone. This is inherently narrower than a cloud provider's account-wide API key. Finer-than-zone scoping (limiting to just the `_acme-challenge` TXT record name) is possible via `update-policy` but its exact syntax should be confirmed against the specific server's documentation before relying on it.
- **Field-by-field mapping**:
  - `server`: the hostname or IP (optionally `host:port`, default port 53) of the authoritative server that will accept the dynamic update — the same server where the zone and TSIG key are configured.
  - `zone`: the exact zone name as declared in that server's `zone "example.com" { ... };` statement — the zone whose `allow-update`/`update-policy` trusts this key.
  - `key_name`: the name chosen when generating the key, e.g. running `tsig-keygen -a hmac-sha256 example-acme-key` uses `example-acme-key` as the key name; this exact name must also appear in the `key` clause in `named.conf` and in the zone's `allow-update`/`update-policy` line.
  - `secret`: the base64 string inside the `secret "...";` line of the `key { ... }` block that `tsig-keygen` prints — copy it verbatim (no extra whitespace) into the plugin field.
  - `algorithm`: whatever was passed to `tsig-keygen -a` (default is `hmac-sha256` if `-a` is omitted); must exactly match the `algorithm` line inside the generated `key { }` clause. Valid choices per ISC's own docs: `hmac-md5`, `hmac-sha1`, `hmac-sha224`, `hmac-sha256`, `hmac-sha384`, `hmac-sha512` (the plugin form lists sha1/sha256/sha512/md5).
- **Credential rotation/revocation guidance**: Run `tsig-keygen` again to generate a new key, add its `key { }` clause to `named.conf` and reference the new key name in the zone's `allow-update`/`update-policy` statement, then apply with `rndc reload` (or `rndc reconfig`). Update the plugin's `key_name`/`secret`/`algorithm` fields to match, confirm a renewal succeeds, then remove the old key's clause and its reference from the zone statement and reload again — this is the revocation step, since there is no central "revoke" action for a locally-generated TSIG key.
- **Common errors and resolutions**:
  - "REFUSED" / update rejected — the `key_name` isn't listed in that zone's `allow-update`/`update-policy` statement, or `server`/`zone`/`key_name`/`secret`/`algorithm` don't all match `named.conf` exactly; an `algorithm` mismatch is a common, silent cause of signature validation failure.
  - Update rejected due to clock skew — TSIG requires the client and server clocks to be within about 5 minutes (the default "fudge" value) of each other; check NTP sync on both the plugin host and the DNS server.
  - SOA/zone errors on update — the target zone isn't actually a primary/master zone on this server (e.g. it's a secondary), and secondaries can't accept dynamic updates; confirm zone type.
  - Secret rejected as malformed — the base64 `secret` value was copied with a trailing newline, truncated, or re-wrapped by an editor; re-copy exactly as `tsig-keygen` printed it.
- **Terms of Service / Privacy Policy**: Not applicable — self-hosted infrastructure.
- **Evidence/verification status**: Verified live against ISC's own BIND 9 documentation (bind9.readthedocs.io, the ISC-maintained docs site) for the `tsig-keygen` synopsis/options/algorithm list and the recommendation to key `allow-update` off TSIG key names rather than IPs. The Debian manpage (a verbatim packaging of the same ISC-authored troff source) was used to fill in the complete option list where the readthedocs fetch was truncated. The exact `update-policy` grant syntax for restricting to a specific record name/type was referenced by ISC as "more flexible" but not independently confirmed — flagged above as **Not independently verified — confirm before publishing**.

Sources: [Manual Pages — BIND 9 9.18.14 documentation](https://bind9.readthedocs.io/en/v9.18.14/manpages.html) — [7. Security Configurations — BIND 9 9.18.14 documentation](https://bind9.readthedocs.io/en/v9.18.14/chapter7.html) — [tsig-keygen(8) — Debian manpages](https://manpages.debian.org/bullseye/bind9/tsig-keygen.8.en.html)

---

## AWS Route 53

- **Credential-creation link**: https://console.aws.amazon.com/iam/ — IAM console → Users → select (or create) a dedicated IAM user for this plugin → the user's **Security credentials** tab → **Create access key**. Verified against AWS's own IAM User Guide.
- **Minimum required permission/scope**: An IAM policy granting `route53:ChangeResourceRecordSets` restricted to the specific hosted zone's ARN — and, for tighter scoping, further restricted with the condition keys `route53:ChangeResourceRecordSetsNormalizedRecordNames` (the `_acme-challenge.<domain>` name), `route53:ChangeResourceRecordSetsRecordTypes` (`TXT`), and `route53:ChangeResourceRecordSetsActions` (`CREATE`, `UPSERT`, `DELETE`) — plus `route53:GetChange` and `route53:ListHostedZones`, both of which AWS's own docs only support granting with `Resource: "*"` (no zone-level restriction exists for those two actions).
- **Zone/project scoping guidance**: Yes for the write action — `ChangeResourceRecordSets` can be scoped to one hosted zone ARN and even to a specific record name/type/action combination (verified with AWS's own example policy JSON below). The tradeoff: `ListHostedZones` and `GetChange` are inherently account-wide actions in IAM — AWS does not support restricting them to one zone — so the credential will always be able to enumerate every hosted zone's name and ID in the account, even though it can only write records into the one zone permitted by the `ChangeResourceRecordSets` statement.

  Example least-privilege policy (adapted from AWS's own documented pattern, substituting the real hosted zone ID and domain):
  ```json
  {
    "Version": "2012-10-17",
    "Statement": [
      {
        "Effect": "Allow",
        "Action": "route53:ChangeResourceRecordSets",
        "Resource": "arn:aws:route53:::hostedzone/Z11111112222222333333",
        "Condition": {
          "ForAllValues:StringEquals": {
            "route53:ChangeResourceRecordSetsNormalizedRecordNames": ["_acme-challenge.example.com"],
            "route53:ChangeResourceRecordSetsRecordTypes": ["TXT"],
            "route53:ChangeResourceRecordSetsActions": ["CREATE", "UPSERT", "DELETE"]
          }
        }
      },
      { "Effect": "Allow", "Action": "route53:GetChange", "Resource": "*" },
      { "Effect": "Allow", "Action": "route53:ListHostedZones", "Resource": "*" }
    ]
  }
  ```
- **Field-by-field mapping**:
  - `access_key_id` / `secret_access_key`: produced together in one step. IAM console → Users → the dedicated user → **Security credentials** tab → **Create access key** → choose the **Command Line Interface (CLI)** use case → Next → (optional tag) → Create → both values are shown once, with a **Download .csv file** option. The Secret Access Key cannot be retrieved again after leaving this screen, so copy both into the plugin's two fields immediately.
- **Credential rotation/revocation guidance**: In IAM → Users → the user → Security credentials, create a second access key (AWS allows a maximum of two active access keys per user), update the plugin with the new pair, confirm a certificate renewal succeeds, then deactivate and delete the old key from the same screen. To revoke immediately (e.g. suspected leak), deactivate or delete the key directly rather than waiting for a renewal cycle.
- **Common errors and resolutions**:
  - `AccessDenied` on `ChangeResourceRecordSets` — the hosted zone ARN in the policy `Resource` doesn't match the zone actually being validated, or a condition value isn't normalized correctly (AWS requires all-lowercase, no trailing dot, and octal-escaped special characters in `ChangeResourceRecordSetsNormalizedRecordNames`) — recheck normalization against AWS's own rules.
  - `InvalidChangeBatch` — the policy's `ChangeResourceRecordSetsActions` condition doesn't include the action the plugin actually sent (e.g. only `CREATE`/`DELETE` allowed but the client sent `UPSERT`) — align the policy with the plugin's actual request pattern.
  - Validation stuck "pending" indefinitely — `route53:GetChange` was denied or was incorrectly scoped to a zone ARN (it only supports `Resource: "*"`) — grant it account-wide as AWS's docs specify.
  - Sudden authentication failures — the access key was deactivated or deleted (e.g. during a rotation) without updating the plugin — check the key's status in the IAM console.
- **Terms of Service / Privacy Policy**: Terms https://aws.amazon.com/agreement/ — Privacy https://aws.amazon.com/privacy/ (as supplied, already verified).
- **Evidence/verification status**: Verified live against AWS's own documentation: https://docs.aws.amazon.com/Route53/latest/DeveloperGuide/specifying-conditions-route53.html (the full IAM policy JSON examples using hosted zone ARN + condition keys, quoted above) and https://docs.aws.amazon.com/IAM/latest/UserGuide/id_credentials_access-keys.html (access key creation/rotation flow, the 2-key-per-user limit, the CLI use-case step). The specific baseline requirement of `ListHostedZones` + `GetChange` alongside `ChangeResourceRecordSets` for a DNS-01 workflow is corroborated via multiple ACME-client ecosystem sources (a Certbot GitHub issue, the `lego` ACME client's Route 53 docs) rather than quoted verbatim from one single AWS page enumerating exactly those three actions together — flagged as corroborated rather than directly verified for that specific 3-action combination.

Sources: [Using IAM policy conditions for fine-grained access control — Amazon Route 53](https://docs.aws.amazon.com/Route53/latest/DeveloperGuide/specifying-conditions-route53.html) — [Resource record set permissions — Amazon Route 53](https://docs.aws.amazon.com/Route53/latest/DeveloperGuide/resource-record-sets-permissions.html) — [Manage access keys for IAM users — AWS IAM](https://docs.aws.amazon.com/IAM/latest/UserGuide/id_credentials_access-keys.html) — [AWS Route 53 least privilege IAM policy for Let's Encrypt DNS challenge | Paul Galow](https://paulgalow.com/aws-route-53-iam-policy-letsencrypt-dns/) — [Amazon Route 53 :: lego ACME client](https://go-acme.github.io/lego/dns/route53/)

---

## Scaleway DNS

- **Credential-creation link**: https://www.scaleway.com/en/docs/iam/how-to/create-api-keys/ — Scaleway console → **IAM & API keys** → **API keys** tab → **+ Generate API key**. Verified live.
- **Minimum required permission/scope**: Scaleway API keys inherit the permissions of their "bearer" (an IAM user or IAM Application) exactly as granted by that bearer's IAM policies, which are built from named "permission sets" (Scaleway's own examples cited in their docs include sets like `InstancesFullAccess`/`InstancesReadOnly`/`RelationalDatabasesFullAccess`). **Not independently verified — confirm before publishing**: the exact name of the Domains-and-DNS-specific permission set. Live documentation fetches of Scaleway's permission-sets reference page and its console policy-builder returned only navigation/menu content rather than the full permission catalog — this looks like the permission table is rendered client-side (JavaScript), which a text-mode fetch does not execute — and no secondary source (Terraform/Pulumi module docs, GitHub) surfaced the specific name either. To find it: Console → IAM → Policies → Create policy → search the "Permission sets" picker for "Domain".
- **Zone/project scoping guidance**: Scaleway API keys/IAM policies are scoped to a "Project" within an Organization (each Project is an isolated resource container), so in principle a key can be restricted to the Project holding only the domain in question. However, this could not be fully confirmed for Domains and DNS specifically — Scaleway's docs reference some resources as Organization-wide rather than per-Project. **Not independently verified — confirm before publishing** whether a single DNS zone can be isolated into its own Project, or whether the relevant DNS permission set is inherently Organization/account-wide.
- **Field-by-field mapping**:
  - `secret_key`: shown once, at creation time, on the "Generate API key" screen (Console → IAM & API keys → API keys). Scaleway generates an **access key** and a **secret key** together as a pair; the plugin's single `secret_key` field corresponds to the secret key value from that pair. Copy it immediately — it cannot be retrieved again after leaving the screen.
- **Credential rotation/revocation guidance**: Console → IAM & API keys → API keys tab → locate the key → delete it to revoke immediately, or let it lapse via an expiration date set at creation. Generate a replacement key pair and update the plugin before deleting the old one, to avoid a gap in renewal capability.
- **Common errors and resolutions**:
  - `401`/`403` from the Scaleway API — the secret key was mistyped/truncated, or the key's bearer lacks a Domains-and-DNS permission set in its attached IAM policy (see the unresolved permission-set name above).
  - Key stops working unexpectedly — Scaleway API keys can be created with an expiration date; check the API keys list for an expired status and generate a replacement.
  - Zone not found — the domain is only registered (or DNS-hosted) elsewhere and not actually added as a Scaleway DNS zone under the same account/Organization the key belongs to; confirm it appears under Console → Domains and DNS.
  - Stale `_acme-challenge` TXT record blocking a new one — a prior failed run left a leftover TXT record at the same name; remove it manually via the console and retry.
- **Terms of Service / Privacy Policy**: Terms https://www.scaleway.com/en/contracts/ — Privacy https://www.scaleway.com/en/privacy-policy/ (as supplied, already verified).
- **Evidence/verification status**: Verified live against https://www.scaleway.com/en/docs/iam/how-to/create-api-keys/ for the key-generation flow and the access-key/secret-key pairing. Could **not** verify the exact DNS-specific IAM permission set name or confirm Project-level isolation for DNS zones specifically — both flagged above as "Not independently verified — confirm before publishing," most likely because Scaleway's permission-sets catalog and policy builder are JavaScript-rendered and not visible to a text-mode fetch.

Sources: [How to create API keys | Scaleway Documentation](https://www.scaleway.com/en/docs/iam/how-to/create-api-keys/) — [Permission sets | Scaleway Documentation](https://www.scaleway.com/en/docs/iam/reference-content/permission-sets/) — [Understanding IAM Policies | Scaleway Documentation](https://www.scaleway.com/en/docs/iam/reference-content/policy/)

---

## Vercel DNS

- **Credential-creation link**: https://vercel.com/account/tokens — Account → Settings → **Tokens** ("Account Tokens" page). Verified live.
- **Minimum required permission/scope**: A **Team**- or **Project**-scoped access token — Vercel's own three token-scoping levels are Full Account / Team / Project. A Project-scoped token, limited to the project that owns the domain, is the narrowest option Vercel exposes; if the domain isn't tied to a single project, use a Team-scoped token instead of Full Account.
- **Zone/project scoping guidance**: Yes — Vercel explicitly supports Team- and Project-scoped tokens, and per Vercel's own docs "a project-scoped token denies any request to another project, to a user-level resource, or to a team-level resource" (verified, quoted from Vercel's documentation). Tradeoff: DNS records in Vercel are managed at the domain/team level, so a Project-scoped token must be scoped to the specific project the domain is attached to; if the domain isn't attached to one project, the narrowest workable option is a Team-scoped token, which also grants access to every other project in that team.
- **Field-by-field mapping**:
  - `api_token`: created at https://vercel.com/account/tokens (or the equivalent Tokens page for a team) → **Create** → name the token → choose **Scope** (Full Account / Team / Project) → choose an expiration → **Create Token** — the value is shown once and must be copied immediately (personal tokens begin with the prefix `vcp_`).
  - `team_id`: leave blank for personal scope, matching the plugin's own placeholder guidance. For a team-scoped credential, find it at the team's own Settings page (`vercel.com/teams/<team>/settings`, "Team ID" field) or via the "List all teams" REST API response's `id` field, which starts with `team_`.
- **Credential rotation/revocation guidance**: On the Account (or team) Tokens page, delete the old token and create a new one with the same scope, update the plugin, and confirm a renewal succeeds. Tokens can also be given an expiration at creation so they lapse automatically without a manual revoke step.
- **Common errors and resolutions**:
  - `403 Forbidden` calling the DNS API — the token's scope doesn't cover the team/project that owns the domain, or a Project-scoped token was used but the domain belongs to a different project; recheck scope against where the domain actually lives.
  - "teamId required"-type errors — a Full-Account token was used without `team_id` set for a domain that lives under a team rather than the personal account; set `team_id` in the plugin, or use a token already scoped to that team.
  - Token stopped working — an expiration was set at creation and has passed; Vercel does not auto-renew tokens, so generate a new one.
  - Token creation blocked — some teams require two-factor authentication or SAML before allowing token creation scoped to them; enable it on the account and retry.
- **Terms of Service / Privacy Policy**: Terms https://vercel.com/legal/terms — Privacy https://vercel.com/legal/privacy-policy (as supplied, already verified).
- **Evidence/verification status**: Verified live against https://vercel.com/docs/accounts/access-tokens (token scoping levels, creation flow, `vcp_` prefix, the exact "denies any request..." quote) and https://vercel.com/docs/rest-api/authentication/create-an-auth-token (`teamId`/`projectId` request parameters). The exact Team ID settings-page location was corroborated via search results referencing that settings page rather than a direct fetch of the live (authenticated) settings screen itself — flagged as corroborated for that one detail.

Sources: [Access tokens | Vercel Docs](https://vercel.com/docs/accounts/access-tokens) — [Create an Auth Token | Vercel REST API](https://vercel.com/docs/rest-api/authentication/create-an-auth-token) — [How do I use a Vercel API Access Token? | Vercel Knowledge Base](https://vercel.com/kb/guide/how-do-i-use-a-vercel-api-access-token)

---

## Vultr DNS

- **Credential-creation link**: https://my.vultr.com/settings/#settingsapi — Vultr Customer Portal → **Account** → **API** (under "OTHER"). Verified: direct fetch of docs.vultr.com succeeded in this session without the Cloudflare bot-check challenge that blocked prior research on this project; findings were also cross-checked via search.
- **Minimum required permission/scope**: None available. Per Vultr's own documentation, "API keys provide full account access without product-specific scoping options" — there is no DNS-only, read-only, or otherwise reduced-privilege role. The only restriction mechanism offered is IP-address allowlisting (Access Control) on the key itself, not a permission scope.
- **Zone/project scoping guidance**: Not possible to scope a key to a single DNS zone/domain — a Vultr API key grants access to every product on the account (compute instances, DNS, block storage, etc.), and Vultr allows only one active API key per account. The only mitigations Vultr documents are: (1) **Access Control** — allowlist the specific IP address(es) of the server running this plugin (verified valid subnet ranges: /8–/32 for IPv4, /20–/128 for IPv6) so the key is unusable from anywhere else, and (2) creating a separate Vultr **Sub-Account** dedicated to DNS hosting, so a compromised key can't reach unrelated production resources in the main account.
- **Field-by-field mapping**:
  - `api_key`: generate at Account → API (`my.vultr.com/settings/#settingsapi`). If API access shows disabled, first click **Enable API** under "Personal Access Token," then use **generate new API key** in the API Key box. Copy it immediately — it is not shown again.
- **Credential rotation/revocation guidance**: On the same Account → API page, regenerate the key to invalidate the old one and issue a new one in a single action (Vultr's docs reference this as key rotation), or disable API access entirely to revoke it outright. Update the plugin with the new key immediately, since regenerating invalidates the old key right away — there is no overlap window.
- **Common errors and resolutions**:
  - `403 Forbidden` despite a correct key — the calling server's IP isn't on the account's Access Control allowlist (if one is configured); add the plugin server's IP under Account → API → Access Control.
  - "API access disabled" errors — personal API access is off by default and was never enabled, or was disabled after a prior rotation; re-enable it at Account → API.
  - Key stopped working unexpectedly — Vultr allows only one API key per account, so if it was regenerated by another process or admin, the plugin's stored key silently breaks; check the current key on the API page and update the plugin, or use a Sub-Account to avoid this collision.
  - Records not updating — confirm the domain is actually added under Vultr's own DNS product (Account → DNS) on the account the key belongs to, not merely registered or hosted elsewhere.
- **Terms of Service / Privacy Policy**: Terms https://www.vultr.com/legal/tos/ — Privacy https://www.vultr.com/legal/privacy/ (as supplied, already verified).
- **Evidence/verification status**: Verified live via direct fetch of docs.vultr.com pages this session (API key creation steps, enabling API access, and the Access Control IP-allowlisting subnet ranges) — the "full account access without product-specific scoping" line is a direct quote from Vultr's own docs. Vultr's separate key-rotation endpoint was referenced only via the docs site's own link list/navigation (not independently opened and read in full in this pass) — flagged as corroborated rather than directly verified for that one specific detail.

Sources: [Create New API Key | Vultr Docs](https://docs.vultr.com/platform/other/api/other-user/create-api-key) — [How to Enable Vultr API Access | Vultr Docs](https://docs.vultr.com/platform/other/api/enable-user-api-access) — [API | Vultr Docs](https://docs.vultr.com/platform/other/other) — [How to Manage Vultr API Access Control | Vultr Docs](https://docs.vultr.com/platform/other/api/manage-api-access-control)

---

## Notable findings for whoever maintains this document or the drivers

A few things surfaced during this research pass that go beyond documentation
and are worth flagging explicitly rather than only noting inline in the
affected provider's own section:

- **Hetzner**: the legacy DNS Console (`dns.hetzner.com`) was fully shut down
  in May 2026 and replaced by per-project tokens in the unified Hetzner
  Console. Any older internal notes referencing the previous flow are stale.
  This is a documentation-only concern for now -- the plugin's driver only
  needs a bearer `api_token`, which the new console still issues -- but it's
  worth a deliberate check that token generation still works exactly as this
  guide describes the next time this document is revisited.
- **GoDaddy**: mid-migration from the classic API Key/Secret model (`sso-key`
  -- the credential pair this plugin's driver expects) to Personal Access
  Tokens, with the classic model scheduled for deprecation later in 2026.
  This may require a driver update, not just a documentation update, before
  that deprecation lands -- tracked as a follow-up, out of scope for this
  documentation pass.
- **DNSPod**: the legacy Token credential pair this plugin's driver actually
  uses (`token_id`/`token`, DNSPod API 2.0) appears to carry the full
  account's DNSPod permissions with no domain-level scoping available at
  all. Fine-grained CAM policies exist only for the separate Tencent Cloud
  SecretId/SecretKey credential type (API 3.0), which is a different pair
  than this plugin asks for. This is a real, non-cosmetic limitation of the
  credential type in use, not a gap in this research.
- **Njalla**: `njal.la` was unreachable to automated fetching for every URL
  attempted during this research, including its own Terms of Service page --
  consistent with the site's own stated design priority of registrant
  anonymity over conventional web infrastructure. Every Njalla fact in this
  document is corroborated only via third-party client libraries and a
  certbot plugin's own documentation, not a direct read of Njalla's own
  material; treat the Njalla section as the least-verified in this document.
- **Bunny.net, DigitalOcean DNS**: account-wide, single-credential models
  with no DNS-only or per-zone scoping option at all -- not a research gap,
  a genuine platform limitation worth surfacing to an administrator choosing
  between providers, not just to whoever configures the field values.

## Future maintenance

Provider account portals, permission models, and credential lifecycles
change over time in ways this document cannot track automatically. Treat
this document the same way `docs/dns-provider-test-matrix.md` treats its own
mocked-vs-live distinction: a snapshot as of the 2026-09-08 research date
above, not a live-synced source of truth. Re-verify a provider's
section directly against its own current documentation before publishing a
significant update to this file, and especially before relying on any line
still marked "Not independently verified."
