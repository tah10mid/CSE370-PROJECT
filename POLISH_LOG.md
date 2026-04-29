# Polish & Animation Log

## Audit (2026-04-29)

> Method: I cannot open a live browser in this session, so the audit was
> done by reading every PHP template and the existing `assets/css/style.css`
> against the spec. Findings flagged below are the gaps a real user would
> see when clicking through the app on XAMPP.

### Landing — `index.php`
- Hero `<h1>`, lead paragraph, and the two CTA buttons all appear instantly with no entrance.
- Below-the-fold "features" grid (3 cards) is static — no scroll reveal as the user scrolls down.
- Buttons (`Get started`, `I already have an account`) have only a flat colour swap on hover; no lift, no shadow change, no press feedback.
- Header is fixed but never reacts to scroll (no shrink / blur).

### Login — `auth/login.php`
- Form card pops in cold; no fade or rise.
- Inputs use only the default browser focus outline — no animated accent ring.
- The error block above the form (`<div class="alert alert-error">`) appears instantly. No shake, no slide-in.
- Submit button has no loading state on click.

### Register — `auth/register.php`
- Same gaps as Login.
- The BRACU email hint (`<small class="muted">`) is a flat line of grey text — easy to miss. No emphasis when the email field is focused.
- Role radio (`student` / `teacher`) has no transition.

### Dashboard — `dashboard.php`
- Three section cards (My Work / Incoming Requests / Latest Messages) appear all at once, no stagger.
- List items inside each card are static (no row-hover, no row-lift).
- "View all" / "+ New" links are flat ghost buttons.
- Empty states (`"You haven't created or joined any work yet."`) are bare text, no illustration, no visual prompt.

### Profile — `profile/index.php`
- Long page (Account / Student Details / Skills & Interests / Teacher Supervision). Sections drop in cold with no entrance.
- The "chips" UI for multivalued attrs is the most kinetic surface in the app, but currently:
  - Adding a chip → full page reload, chip just appears.
  - Removing a chip (×) → full page reload, chip just disappears.
- Save buttons have no success animation; the success flash above is a flat green box.
- Student ID line (`Student ID: 1`) is plain text. Could be a cleaner badge.

### Browse — `projects/index.php`
- Filter bar (search + type + mine + Filter button) is static; no transition on submit.
- Grid of work cards: cards have no hover lift, no shadow growth, no accent border.
- "+ Post new work" CTA in the page-head is a plain primary button.
- Empty results show only `"No work matches the filters."` — no illustration, no helpful CTA.

### Project Create — `projects/create.php`
- Single bare form. Title, Description, Type, Supervisor, Status. No floating labels, no live char count.
- Cancel / Create buttons sit flat side-by-side.
- No loading state when submitting.

### Project Detail — `projects/view.php`
- Title block + tags appear instantly.
- "Team" / "Status history" / "Supervision" / "Edit" sections are stacked cards with no entrance stagger.
- Status chips have a × form-button identical to the chip pattern in profile — same lack of motion.
- Edit + Delete buttons sit flat next to each other; Delete is just a red button (no confirmation dialog beyond the native `confirm()`).
- Join / Leave / Take / Drop supervision actions all hit the server then full reload — no inline feedback.

### Team Requests — `requests/index.php`
- Two `<table>` blocks (Incoming / Outgoing). Tables look spreadsheet-ish, no row hover, no row entrance.
- Accept / Reject / Cancel buttons are inline. No animation on action — just a reload.

### Messages Inbox — `messages/index.php`
- Two-column `grid-2`: Conversations list + Start-new-chat select.
- Conversation rows static. Long body text truncated with `...` but no hover preview.
- Empty state is plain `"No conversations yet."`.

### Messages Thread — `messages/thread.php`
- Chat bubbles are visually OK (mine vs theirs colour split) but they all appear at once on load — no slide-in.
- Sending a message reloads the page (because of `redirect`) so there is no live "send" feel.
- Compose textarea has no auto-grow, no submit-on-Enter.
- Back button is a plain ghost link.

### Header / Global Nav (`includes/header.php`)
- Brand + nav links + auth buttons. Active nav link has no indicator at all (you can't tell which page you're on).
- Logout / Register buttons are flat.
- No mobile menu — at narrow widths the nav simply wraps.
- Flash messages (`alert-success` / `alert-error`) render in the page flow above `<main>`; they don't slide in, don't auto-dismiss, and don't show a progress drain.

### Footer (`includes/footer.php`)
- One-line greyed text. Fine as-is. No motion needed.

### Cross-cutting issues
- No `motion.css` / motion tokens — every transition that does exist is hand-coded `0.15s` in style.css.
- Zero JavaScript on the site so far. No `assets/js/` content even though the folder exists.
- No `prefers-reduced-motion` media query anywhere.
- No 404 page — Apache default is shown for bad routes.
- No skeleton loaders (irrelevant currently because there's no async fetch — every action is a full page reload).
- No SVG illustrations for empty states.

---

## Animation Inventory (proposed, NOT yet implemented)

Grouped by category. Each item maps to a section in Step 4.

### Page entrance (every page)
- `<main>` content: opacity 0→1, translateY 8px→0, `--motion-base` `--ease-out`.
- Hero on landing: stagger direct children (60–80ms gap).

### Scroll reveals
- `[data-reveal]` on the three feature cards on landing, plus dashboard cards.
- IntersectionObserver, threshold 0.15, `rootMargin: "0px 0px -10% 0px"`, `unobserve` after first reveal.

### Card micro-interactions
- `.card` and `.work-card`: hover → `translateY(-2px)` + shadow grow + accent border. Active → `scale(0.98)`.
- Apply `will-change: transform` only on `:hover`.

### Button polish
- All `.btn`: hover lift 1px + shadow grow, active `scale(0.97)`.
- `.btn[type=submit]` inside a `<form>` gets a JS-driven loading state (label → spinner) on `submit`.

### Form inputs
- Animated focus ring (2px accent, 120ms fade in).
- Floating labels for the auth forms (login + register) and the project-create form.
- Red shake on alert-error appearance (3 horizontal moves, 300ms total).

### Chips (profile + project status)
- Add: scale 0.8→1 with `--ease-spring`, fade-in.
- Remove: fade + scale down.
- Need this without breaking the existing form-submit-and-reload flow → CSS-only entrance using `@starting-style` or a one-time JS listener that runs on page load and tags newly-added chips. (Decision below.)

### Modals & toasts
- No modals in the app right now. Skip until needed.
- Flash messages → upgrade to slide-in toasts from top-right with auto-dismiss + drain bar.

### Navigation
- Sticky header: at `scrollY > 80`, add `.is-condensed` (smaller padding + `backdrop-filter: blur(12px)` + semi-transparent bg). 240ms transition.
- Animated underline under the active nav link using `::after` + `transform: scaleX(1)`.

### Lists & empty states
- Browse cards: 30ms stagger fade-in on first paint.
- Empty states get a small inline SVG (line-art style) + the existing message + a CTA.

### Messages
- Chat bubbles: stagger fade + 8px rise on initial load (40ms apart, capped at 12 bubbles).
- Composer textarea: auto-grow up to 6 lines, Ctrl/Cmd+Enter submits.

### Dashboard stats
- N/A — there are no stat numbers in the current dashboard. Skip until we add some.

---

## Decisions

- **Animation library:** Vanilla CSS for state-driven things (hover/focus/press/transitions) + ~30 lines of vanilla JS (IntersectionObserver, scroll-condense, form-submit spinner, flash-toast, chat composer enhancements). **No GSAP.** The motion budget here is small enough that a library would be overweight.
- **Easing curves:** `--ease-out: cubic-bezier(0.16, 1, 0.3, 1)`, `--ease-in-out: cubic-bezier(0.65, 0, 0.35, 1)`, `--ease-spring: cubic-bezier(0.34, 1.56, 0.64, 1)`.
- **Duration tokens:** `--motion-instant: 100ms`, `--motion-fast: 180ms`, `--motion-base: 240ms`, `--motion-slow: 360ms`, `--motion-slower: 600ms`.
- **File layout:** new `assets/css/motion.css` (tokens + animation classes) loaded after `style.css`. New `assets/js/app.js` loaded with `defer` from `includes/footer.php`. Zero PHP logic changes.
- **Reduced motion:** global `@media (prefers-reduced-motion: reduce)` block in `motion.css` + a JS guard so observers don't add classes that would still flash.

---

## Done

- [x] Audit complete
- [x] Motion tokens added to motion.css
- [x] Page transitions (main fade + rise; hero stagger)
- [x] Scroll reveals (data-reveal + IntersectionObserver)
- [x] Card / button micro-interactions (hover lift, active scale, shadow grow)
- [x] Form interactions (animated focus ring, label-on-focus highlight, error shake)
- [x] Toasts (flash → fixed top-right slide-in with 4s drain bar)
- [x] Navigation polish (animated underline + active link + sticky condense)
- [x] Loading states (submit button spinner, doesn't preventDefault)
- [x] Empty states polish (SVG + CTA on dashboard, browse, messages)
- [x] Reduced-motion fallbacks (global @media block + JS guard)
- [ ] Performance pass — needs to be run locally (Lighthouse not available
      in this sandbox). Static review only: every animation uses transform/
      opacity, will-change is implicit, observers unobserve after first
      reveal. Should be 60fps on any modern browser.

## Issues / Notes

- Two limitations of doing this in the current sandbox: (1) no live browser → no real Lighthouse / DevTools recording, so the perf pass will rely on code review of `will-change` usage and animated properties; (2) no Node/npm available, so any "library" choice has to ship as a single CDN/`<script>` tag or be vanilla.
- The whole app navigates by full page reload (no SPA behaviour). That means animations like "chip add" can't be naturally driven by a state change — they happen on the next page paint. The plan above accounts for that with on-load class tagging instead of mid-page transitions.
