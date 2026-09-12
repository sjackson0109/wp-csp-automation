# SAM Portal (`vcns/sam-portal`)
## Delivery Status, Capability Ideation, and Open Questions

**Product:** VCNS SAM Platform -- portal component
**Repository:** `vcns/sam-portal` (does not exist yet -- confirmed against GitHub, 12 September 2026)
**Companion document:** `docs/sam-portal-requirements-spec.md` -- that document is the fixed architecture and requirements baseline (rewritten 2026-09-04; its own §24 "Decisions Fixed by This Specification" settles naming, repo boundaries, and non-negotiable security properties). **This document does not repeat or reinterpret that spec.** It tracks delivery status, breaks the spec's §20 delivery plan into a concrete feature backlog, and holds the open questions that are genuinely product/sequencing decisions rather than architecture -- the kind of living tracker `.roadmap/phase4_plan.md` is for `vcns/security-automation-manager`.
**Status:** Pre-implementation. Nothing in `vcns/sam-portal` itself has been built. Split out of `.roadmap/phase4_plan.md`'s former Phase 4E.2 entry on 12 September 2026 because this is a separate product with its own repository, delivery plan, and (eventually) release cadence -- it doesn't fit that document's WordPress-plugin-scoped phase numbering.
**Date:** 12 September 2026

---

# 1. Where This Sits Relative to the Rest of SAM

Three repositories, three independent delivery tracks:

- `vcns/security-automation-manager` (this repo) -- tracked in `.roadmap/phase4_plan.md`. Phase 4A-4D and 4G delivered; 4E.1 (tier packaging) delivered; 4F (Recommendations Engine) not started.
- `vcns/sam-licensing-service` -- has no roadmap doc in this repo (it's a separate repository with its own history), but its progress is directly relevant here since the portal consumes it. **Confirmed 12 September 2026:** repository rename done; that service's own Phase 0-4 (production hardening, service-identity auth, licensing-model breadth, Stripe subscription adoption, audit logging) and a catalog/admin-portal track (PRs #14-19: KV-backed catalogue, coupons, admin-write endpoints, admin-auth foundation, a session-based admin portal shell) are merged. PR #13 (per-installation fleet detail) was correctly closed unmerged after a design-flaw review. Uncommitted local WIP adds a full Cloudflare Custom Domain deploy pipeline (render-script + GitHub Actions staging/production workflow + 9 numbered docs). None of this is `sam-portal` work, but it's directly reusable: see §3 below.
- `vcns/sam-portal` -- this document. Not started.

---

# 2. Current Status

Nothing to report beyond "not started." No repository, no code, no CI/CD, no threat model. The spec's entire §20 delivery plan -- Foundation through Assurance and fleet expansion -- is ahead of us.

The one substantive fact worth restating here (moved from `phase4_plan.md`'s old 4E.2 entry): `sam-licensing-service`'s existing, merged work already gives the portal a proven, working pattern for several things it will need on day one -- Ed25519 service-identity auth, a session-based admin-auth model, KV-backed storage, and a Cloudflare Custom Domain deploy pipeline with staging/production GitHub Environments. None of that is portal code, but none of it needs to be reinvented either. See §3's Foundation section.

---

# 3. Capability Backlog, by Delivery Phase

The spec's §20 gives four dependency-ordered phases with one-line bullets. Below, each bullet is expanded into something closer to a feature backlog, plus sequencing judgment -- not new architecture, just breaking the spec's requirements into a buildable order. Spec section references are in brackets so nothing here drifts from the fixed baseline.

## 3.1 Foundation

- **Create the `vcns/sam-portal` repository.** Ownership, CI/CD, environments (staging/production, matching `sam-licensing-service`'s existing pattern), architecture decision records. *Open question §5.1: what stack.*
- **Threat model and data-flow inventory** [§17, §18, §23] -- the spec makes this a Definition-of-Done item, not a nice-to-have; worth doing before the first line of ingestion/auth code, not after.
- **Publish the SAM protocol schemas and cross-runtime test vectors** [§8, §19] -- this is the one artifact all three repositories need to agree on without a runtime dependency between them. Concretely: a versioned schema package (JSON Schema or similar) that `security-automation-manager` (PHP) and `sam-portal` (whatever its runtime turns out to be) both validate against. This can live in its own small repo, or inside `sam-portal` if the portal is the natural owner -- worth deciding once the portal's own stack is chosen.
- **Remove production Stripe secrets from every customer-controlled WordPress path** [§21.2] -- this is largely a `security-automation-manager`-side cleanup (confirm no direct-Stripe compatibility path remains, rotate any previously-distributed credentials), not portal-repo work, but it's listed here because the spec places it in this phase and it blocks nothing else -- can happen independently, any time.

## 3.2 Portal minimum viable service

The spec's own MVP phase is still seven substantial items. A suggested build order, prioritising what delivers customer-visible value fastest and what has the fewest upstream dependencies:

1. **Tenant auth and RBAC** [§6] -- the 6-role model (Owner/Administrator/Approver/Analyst/Viewer/Service identity) is spec-fixed. `sam-licensing-service`'s admin-auth foundation (password hashing, admin users, sessions -- PR #18) is the closest existing pattern to extend, not a from-scratch build.
2. **CSP reporting ingestion and safe normalisation** [§9] -- arguably the single highest-leverage first customer-facing feature: it's the one thing that works in Portal-only mode [§3.2] with zero WordPress dependency, so it's what makes a non-WordPress customer possible at all, and it's a bounded, well-specified problem (accept/reject/normalise/neutralise, no scanning-safety surface to design yet).
3. **Protected-resource enrolment and domain verification** [§7] -- needed before ingestion can be tenant-scoped for real. Smallest defensible v1: DNS TXT only, defer HTTP well-known and adapter-based enrolment to a later slice.
4. **Findings, evidence and notification workflow** [§11] -- depends on ingestion existing first; this is where "a report came in" becomes "here's what it means."
5. **Licensing-service Checkout, subscription and entitlement integration** [§15] -- mostly wiring against an API that already exists server-side (`sam-licensing-service`'s Phase 2/Phase 3 work covers Checkout and subscriptions already) -- less new design than it sounds.
6. **Public external header/CSP/TLS scanning** [§10] -- deliberately sequenced later within MVP, not because it's unimportant, but because §10.1's scan-safety requirements (SSRF prevention, DNS re-check at connection time, blocked-range enforcement, per-tenant/global kill switches) are a substantial standalone engineering effort with real infrastructure cost (egress, compute, abuse potential) -- worth its own focused build once ingestion and the dashboard shell exist to show its output in. *Open question §5.4.*
7. **Portal-only setup instructions for common host-header configurations** [§14 onboarding] -- documentation-shaped work, can trail the features it documents.

## 3.3 Integrated SAM

- WordPress enrolment, signed/encrypted evidence upload, fleet posture, signed policy proposals, local approval/rollback, offline queues and key rotation [§3.3, §8, §12].
- This is where `security-automation-manager` gains new work of its own (a future phase on that repo's side -- not tracked here, would need its own entry in `phase4_plan.md` or a successor once scoped). It can't start meaningfully until the portal's tenant/resource/policy-versioning model exists to enrol into, so it's correctly sequenced after Portal MVP, not in parallel with it.

## 3.4 Assurance and fleet expansion

- Cross-resource governance, dependency/payload intelligence, certificate/drift risk, broader adapters (proxy/Linux/Windows/Docker/Kubernetes), federated intelligence, time-bound exceptions and advanced simulation [§13, §10.2].
- Last, deliberately -- same rationale `phase4_plan.md` already gives for Phase 4F (Recommendations Engine): this class of work benefits from real operational data existing first, and there's no fleet to manage until customers with multiple protected resources exist.

---

# 4. What This Document Deliberately Does Not Decide

Per the companion spec's §24, these are already fixed and this document does not revisit them: the three-repository boundary, `sam-portal` as the fleet/assurance/hosted-reporting owner, no shared writable database between components, Stripe secrets confined to the licensing service, mutual authentication/signing/encryption for privileged component messages, and typed/bounded remote control (no arbitrary remote execution).

---

# 5. Open Questions

These are sequencing, resourcing, and scope decisions -- not architecture, which the spec already settles. Flagging them here rather than guessing.

## 5.1 Stack for `vcns/sam-portal`

The spec is stack-agnostic. `sam-licensing-service` is Cloudflare Workers + KV + Durable Objects, and its admin-auth/deploy-pipeline patterns are directly reusable *if* the portal uses the same stack. But the portal is a materially bigger surface than a licensing worker -- multi-tenant dashboards, fleet views, findings/evidence browsing -- which is a different shape of problem than a lightweight API worker. Worth an explicit decision rather than defaulting to "same as licensing-service" by inertia.

## 5.2 Sequencing against Phase 4F

`phase4_plan.md` had SAM Portal build sequenced before Phase 4F (Recommendations Engine) in its own recommendation, but that was written before either had a concrete start date. Given `sam-portal` doesn't exist yet and 4F is "zero implementation, lowest priority, deliberately" -- does Foundation-phase portal work start now, does 4F get scoped/started first, or do both proceed in parallel (accepting that they don't share code or blockers)?

## 5.3 First build slice

Section 3.2 above suggests CSP ingestion as the highest-leverage first customer-facing feature, with tenant auth/RBAC as its necessary prerequisite. Does that match the intended starting point, or is there a different first slice in mind -- e.g. leading with the licensing/Checkout integration since much of its server side already exists, or leading with tenant auth plus a bare-bones dashboard shell before any data-ingestion feature at all?

## 5.4 External scanning: build, defer, or buy

Section 10's scan-safety requirements (SSRF prevention, controlled egress, per-tenant kill switches, bounded redirects/bytes/time) are a real, ongoing engineering and infrastructure-cost commitment. Three options worth weighing: build it in-house as specified (full control, full cost), defer it to a later milestone entirely (ship CSP-ingestion-only value first, add scanning once there's a paying customer base to justify the infra spend), or evaluate whether a third-party scanning/monitoring API could satisfy some of §10's requirements at lower engineering cost (would need its own review against §10.1's safety requirements and the non-goals in §22 before adopting).

## 5.5 Solo build or scoped-for-help

Everything shipped so far across all three repositories has been built through this same Claude-Code-assisted, single-operator workflow. The portal is a bigger, longer-lived surface than anything built to date. Worth a deliberate check-in on whether that continues to be the intended build model for `sam-portal` specifically, since it affects how much upfront architecture documentation versus just-in-time decision-making makes sense.
