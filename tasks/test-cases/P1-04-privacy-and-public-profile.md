# P1-04 Privacy settings and public profile: test cases

Source: tasks/phase-1/P1-04-privacy-and-public-profile.md; specs/07 `privacy_settings` / `user_stats`, specs/04 §1–§3, specs/11 "Account enumeration", specs/17 §6, specs/18 §6, FR-PROFILE-4–6.

Change visibility through `/settings/privacy`, not Adminer: the privacy row is cached for an hour and only the page
write refreshes it. Console snippets need `const t = decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)[1]);`
first; `H` below stands for `{'Content-Type': 'application/json', Accept: 'application/json', 'X-XSRF-TOKEN': t}`.
"Guest" means a private window with nobody signed in.

## Happy path

### TC-P1-04-001: Privacy settings page renders with the defaults
- Priority: High · Type: Functional
- Ref: FR-PROFILE-4; task Open questions 4–5
- Preconditions: fresh seed; signed in as `test_user`.
- Steps:
  1. Open `/settings/profile` and click "Privacy" in the settings sub-nav.
  2. Read the form.
- Expected: URL `/settings/privacy`, tab title "Privacy settings · Clash Commons", "Privacy" active in the sub-nav. Radio group "Who can see your profile" with "Everyone" (selected, gold border), "Signed-in members" and "Only me", each with its description ("Anyone can see your profile, including search engines if you allow it below." / "Only people signed in to Clash Commons can see your profile." / "Nobody else can see your profile. Visitors get a page not found."). "On your profile": "Show my Clash of Clans accounts" and "Show my clan", both on, each with "Applies once account linking opens.". "Contact and search": "Let clans contact me about recruitment" on, "Show my profile in search results" on with "Search engines only list it while everyone can see your profile.". No activity or marketplace toggles. "Save privacy settings" and a "View your profile" link to `/u/test_user`.

### TC-P1-04-002: Save privacy changes
- Priority: High · Type: Functional
- Ref: FR-PROFILE-4
- Preconditions: signed in as `test_user` on `/settings/privacy`.
- Steps:
  1. Pick "Signed-in members", switch off "Show my clan" and "Show my profile in search results", click "Save privacy settings".
  2. Reload; in Adminer `SELECT * FROM privacy_settings WHERE user_id = (SELECT id FROM users WHERE username = 'test_user');`
- Expected: "Saved." appears beside the button. After reload the choices persist. DB: `profile_visibility` = `members`, `show_clan` = false, `searchable` = false, the other columns unchanged (`show_activity` true, `allow_marketplace_contact` false).

### TC-P1-04-003: New accounts get default privacy and stats rows
- Priority: Medium · Type: Functional
- Ref: specs/07 `privacy_settings`, `user_stats`; task Scope "Migrations"
- Preconditions: signed out.
- Steps:
  1. Register a new account `privacy_new` at `/register` (wait a few seconds before submitting).
  2. In Adminer query `privacy_settings` and `user_stats` for the new user's id.
- Expected: One row in each. `privacy_settings`: `profile_visibility` `public`, `show_coc_accounts`, `show_clan`, `show_activity`, `allow_recruitment_contact` and `searchable` true, `allow_marketplace_contact` false. `user_stats`: every counter 0, `recomputed_at` NULL.

### TC-P1-04-004: Public profile shows every filled field
- Priority: High · Type: Functional
- Ref: FR-PROFILE-5; specs/18 §6 "Player profile"
- Preconditions: `test_user` has display name `Clash Chief`, bio, country Germany, languages English and German, a ready avatar, socials set (P1-03); visibility Everyone.
- Steps:
  1. As a guest open `/u/test_user`.
- Expected: 200. Cover band: the avatar photo, heading "Clash Chief", "@test_user", "Germany", "Member since <Month Year of sign-up>", "Speaks English, German". Stat blocks "Bases", "Likes received", "Copies", all 0. "About" with the bio (line breaks kept) and the social links. Tabs "Accounts" and "Bases". No "Edit profile" button.

### TC-P1-04-005: Missing fields fall back cleanly
- Priority: Medium · Type: Functional
- Ref: FR-PROFILE-5; task Decisions 8
- Preconditions: `test_moderator` has no display name, bio, country, languages, socials or avatar (fresh seed).
- Steps:
  1. As a guest open `/u/test_moderator`.
- Expected: Heading "test_moderator", initials avatar "T", "@test_moderator" and "Member since …" only (no country or "Speaks" line). No "About" section. Stats and tabs still render.

### TC-P1-04-006: Own profile shows owner controls and own empty states
- Priority: Medium · Type: Functional
- Ref: task Scope "Page `Profile/Show`"; Decisions 9
- Preconditions: signed in as `test_user`.
- Steps:
  1. Open the avatar menu and choose "Your profile".
  2. Look at the header buttons and both tabs.
- Expected: URL `/u/test_user`. "Edit profile" (to `/settings/profile`) and "Privacy" (to `/settings/privacy`) buttons. Accounts tab: "No accounts yet" / "Your verified Clash of Clans accounts will show here once account linking opens.". Bases tab: "No bases yet" / "Bases you publish will show here once publishing opens.". No call-to-action buttons inside the empty states.

### TC-P1-04-007: Someone else's profile shows muted empty states
- Priority: Medium · Type: Functional
- Ref: task Scope "Page `Profile/Show`"
- Preconditions: signed in as `test_moderator`; `test_user` is public.
- Steps:
  1. Open `/u/test_user`; switch between both tabs.
- Expected: No "Edit profile" or "Privacy" buttons. Accounts: "No public accounts."; Bases: "No published bases yet." in muted text.

### TC-P1-04-008: Profile and privacy changes apply at once
- Priority: High · Type: Functional
- Ref: specs/21 §3 (`profile:{username}`, `user:{id}:privacy`); task Review fixes
- Preconditions: `test_user` public; a guest window on `/u/test_user` and a signed-in `test_user` window.
- Steps:
  1. As `test_user` change the bio and save; reload the guest window.
  2. Set visibility "Only me" and save; reload the guest window.
  3. Set "Everyone" and save; reload the guest window.
- Expected: Step 1: the new bio shows immediately (no 5-minute delay). Step 2: the guest gets the 404 page immediately. Step 3: the profile is back immediately.

### TC-P1-04-009: Username in the URL is case-insensitive
- Priority: Low · Type: Edge case
- Ref: task Decisions 3 (citext usernames)
- Preconditions: `test_user` public.
- Steps:
  1. As a guest open `/u/TEST_User`; view the page source.
- Expected: The profile renders (200, no redirect). "@test_user" shows in lowercase and `<link rel="canonical">` is `http://localhost:8080/u/test_user`.

## Visibility × viewer

### TC-P1-04-010: "Everyone" profile is visible to all viewers
- Priority: High · Type: Authorization
- Ref: FR-PROFILE-4; task Acceptance "Authorization"
- Preconditions: `test_user` visibility "Everyone".
- Steps:
  1. Open `/u/test_user` as a guest, as `test_moderator` (member/staff), as `test_admin`, and as `test_user`.
- Expected: All four get the profile (200). Only `test_user` sees "Edit profile".

### TC-P1-04-011: "Signed-in members" profile hides from guests only
- Priority: High · Type: Authorization
- Ref: FR-PROFILE-4; task Open question 2
- Preconditions: `test_user` visibility "Signed-in members"; a second ordinary account (e.g. `privacy_new` from TC-P1-04-003).
- Steps:
  1. As a guest open `/u/test_user`.
  2. Sign in as `privacy_new`, open it. 3. Sign in as `test_moderator`, open it. 4. As `test_user`, open it.
- Expected: Step 1: the "We couldn't find that profile" page with status 404 and no sign-in prompt. Steps 2–4: the profile renders.

### TC-P1-04-012: "Only me" profile is visible to the owner only
- Priority: High · Type: Authorization
- Ref: FR-PROFILE-4; task Open question 1
- Preconditions: `test_user` visibility "Only me".
- Steps:
  1. Open `/u/test_user` as a guest and as `privacy_new`.
  2. Open it as `test_user`.
- Expected: Step 1: the 404 page for both. Step 2: the profile renders with the owner controls.

### TC-P1-04-013: Staff get no bypass on a hidden profile
- Priority: High · Type: Authorization
- Ref: task Open question 6; specs/04 §3
- Preconditions: `test_user` visibility "Only me".
- Steps:
  1. Open `/u/test_user` as `test_moderator`, `test_admin` and `test_super_admin`.
- Expected: Each gets the same 404 page as a guest; nothing hints that the profile exists.

### TC-P1-04-014: Banned owner's profile returns 404
- Priority: High · Type: Authorization
- Ref: task Open question 3; specs/04 §1
- Preconditions: `test_user` public; in Adminer `UPDATE users SET status = 'banned' WHERE username = 'test_user';`
- Steps:
  1. Open `/u/test_user` as a guest, as `test_moderator` and as `test_admin`.
  2. Reset: `UPDATE users SET status = 'active' WHERE username = 'test_user';`
- Expected: 404 page for every viewer. After the reset the profile renders again on the next load.

### TC-P1-04-015: Pending-deletion owner's profile returns 404
- Priority: High · Type: Authorization
- Ref: task Open question 3
- Preconditions: `privacy_new` public, signed in as `privacy_new`.
- Steps:
  1. Go to Settings, Danger zone, enter the password and request deletion.
  2. As a guest and as `test_admin` open `/u/privacy_new`.
- Expected: Signed out with the deletion notice; `/u/privacy_new` gives the 404 page to both viewers. (Signing in again as `privacy_new` cancels the deletion and the profile returns.)

### TC-P1-04-016: Suspended owner's profile stays visible
- Priority: Medium · Type: Authorization
- Ref: task Open question 3
- Preconditions: `test_user` public; in Adminer `UPDATE users SET status = 'suspended', status_expires_at = NULL WHERE username = 'test_user';`
- Steps:
  1. As a guest open `/u/test_user`.
  2. Reset the status to `active`.
- Expected: The profile renders (200).

### TC-P1-04-017: Unknown and malformed usernames return 404
- Priority: High · Type: Edge case
- Ref: specs/11 "Account enumeration"; task Review fixes "unstorable names"
- Preconditions: none.
- Steps:
  1. As a guest open `/u/nobody_here`, `/u/a-b`, `/u/abcdefghijklmnopqrstu` (21 characters) and `/u/%E2%9C%93`.
  2. Open `/u/%FF`.
- Expected: Step 1: each gives the 404 page "We couldn't find that profile" (no 500). Step 2: the framework answers 400 Bad Request before the app (accepted per task Review fixes); no 500.

### TC-P1-04-018: Every miss returns the same 404
- Priority: High · Type: Security
- Ref: task Acceptance "Edge cases"; Decisions 6
- Preconditions: `test_user` set to "Only me"; `test_moderator` banned in Adminer; `privacy_new` pending deletion (TC-P1-04-015).
- Steps:
  1. As a guest open view-source for `/u/nobody_here`, `/u/test_user`, `/u/test_moderator` and `/u/privacy_new`; in DevTools Network note each status.
  2. Compare the `<title>`, robots meta and the `data-page` JSON of the four.
  3. Reset `test_moderator` to `active`.
- Expected: All four answer 404 with `<title>Profile not found · Clash Commons</title>`, `<meta name="robots" content="noindex, nofollow">`, component `Profile/NotFound` and no profile props. The only differences are the requested path (canonical, `og:url`, the `url` in `data-page`).

## SEO

### TC-P1-04-019: Head tags of an indexable profile
- Priority: High · Type: Functional
- Ref: specs/17 §6; task Scope "SEO"
- Preconditions: `test_user` "Everyone" with search on; display name `Clash Chief`, a bio, a ready avatar, YouTube, X and Discord handles.
- Steps:
  1. As a guest open `view-source:http://localhost:8080/u/test_user`.
- Expected: `<title>Clash Chief (@test_user) · Clash Commons</title>`; `meta description` = the bio; `link rel="canonical"` `http://localhost:8080/u/test_user`; no robots meta; `og:type` `profile`, `og:title`, `og:url`, `og:image` (the 512 px avatar URL). One `application/ld+json` script with `"@type":"Person"`, `name` "Clash Chief", `alternateName` "@test_user", `url`, `image`, `description` and `sameAs` holding the YouTube and X URLs (no Discord entry).

### TC-P1-04-020: Title and description fallbacks
- Priority: Medium · Type: Functional
- Ref: task Decisions 8
- Preconditions: `test_moderator` with no display name and no bio; then give it display name `Mod Squad`, still no bio.
- Steps:
  1. View source of `/u/test_moderator` before and after adding the display name.
- Expected: Before: title "@test_moderator · Clash Commons", description "test_moderator on Clash Commons.", JSON-LD without `description`, `image` or `sameAs`. After: title "Mod Squad (@test_moderator) · Clash Commons", description "Mod Squad on Clash Commons.".

### TC-P1-04-021: Long or multi-line bio is squished and cut to 160 characters
- Priority: Low · Type: Edge case
- Ref: `platform.profile.meta_description_max` 160
- Preconditions: `test_user` bio of three lines totalling about 300 characters.
- Steps:
  1. View source of `/u/test_user`.
- Expected: `meta description` and `og:description` are one line (line breaks and repeated spaces collapsed), 160 characters plus "...". The page body still shows the full bio with its line breaks.

### TC-P1-04-022: noindex unless public and searchable
- Priority: High · Type: Functional
- Ref: task Scope "SEO" (`noindex` unless public and searchable)
- Preconditions: signed in as `test_user` in one window; view source as a guest or as `test_user` as noted.
- Steps:
  1. "Everyone" with search off: view source as a guest.
  2. "Signed-in members" with search on: view source as `test_moderator`.
  3. "Only me": view source as `test_user`.
  4. "Everyone" with search on: view source as a guest.
- Expected: Steps 1–3 include `<meta name="robots" content="noindex, nofollow">`. Step 4 has no robots meta.

### TC-P1-04-023: Profile is server-rendered
- Priority: Medium · Type: Functional
- Ref: specs/17 §6 (SSR); task Scope `GET /u/{username}` (public, SSR)
- Preconditions: `test_user` public with a bio; the `ssr` container running.
- Steps:
  1. View source of `/u/test_user` as a guest and search the body (outside `data-page`) for the display name and bio.
  2. Disable JavaScript in DevTools settings and reload.
- Expected: The heading, `@test_user`, bio and stat labels are in the server HTML. With JavaScript off the profile still reads correctly.

### TC-P1-04-024: Canonical ignores case and query string
- Priority: Low · Type: Functional
- Ref: specs/17 §6 canonical
- Preconditions: `test_user` public.
- Steps:
  1. View source of `/u/Test_User?ref=discord`.
- Expected: Canonical and `og:url` are `http://localhost:8080/u/test_user` with no query string.

## Security

### TC-P1-04-025: Page props carry no private data
- Priority: High · Type: Security
- Ref: specs/11 "Data exposure via page props"; task Tests "Security"
- Preconditions: `test_user` public with every field filled.
- Steps:
  1. As a guest view source of `/u/test_user`; copy the `data-page` attribute and search it.
  2. Repeat signed in as `test_user`.
- Expected: The `profile` prop holds only username, display name, avatar URLs, bio, country, languages, socials, member-since, stats and `isOwn`. No email, role, status, numeric or ULID ids, `profile_visibility`, `searchable` or other privacy flags (the signed-in shared `auth` block is P1-02's and not part of `profile`).

### TC-P1-04-026: Bio and display name are escaped in HTML and JSON-LD
- Priority: High · Type: Security
- Ref: specs/11 "Stored XSS"; task Decisions 12
- Preconditions: signed in as `test_user`.
- Steps:
  1. Set display name `Chief "&" <3` and bio `< script>alert(1)< /script> and </ script> & 'x'`; save.
  2. As a guest open `/u/test_user` and view its source.
- Expected: No alert. The page shows the text literally. In the server HTML the body text is escaped (`&lt; script&gt;`, `&amp;`), meta description and `og:` values are attribute-escaped, and the JSON-LD script uses `<`, `>`, `&`, `'`, `"` escapes, so nothing closes the script tag early.

### TC-P1-04-027: Social links use fixed hosts and safe rel
- Priority: High · Type: Security
- Ref: FR-PROFILE-6; P1-03 follow-up
- Preconditions: `test_user` socials YouTube `@clashchief`, Twitch `clashchief_tv`, X `clash_chief`, Discord `clash.chief`; public.
- Steps:
  1. As a guest open `/u/test_user`; inspect each link in the About section.
- Expected: "YouTube: @clashchief" → `https://www.youtube.com/@clashchief`; "Twitch: clashchief_tv" → `https://www.twitch.tv/clashchief_tv`; "X: clash_chief" → `https://x.com/clash_chief`; each `rel="nofollow ugc noopener"`. "Discord: clash.chief" is plain text, not a link.

### TC-P1-04-028: Mass assignment on the privacy update does nothing
- Priority: High · Type: Security
- Ref: specs/11 "Mass assignment"; task Tests "Security"
- Preconditions: signed in as `test_user`, CSRF token `t` set; note `role`, `status` and the full `privacy_settings` row.
- Steps:
  1. Run `fetch('/settings/privacy', {method: 'PATCH', headers: H, body: JSON.stringify({profile_visibility: 'public', show_coc_accounts: true, show_clan: true, allow_recruitment_contact: true, searchable: true, user_id: 1, role: 'admin', status: 'active', show_activity: false, allow_marketplace_contact: true})}).then(r => console.log(r.status))`.
  2. Re-check the user and privacy rows in Adminer.
- Expected: The request succeeds; `role`, `status`, `privacy_settings.user_id`, `show_activity` (still true) and `allow_marketplace_contact` (still false) are unchanged. No other user's row changed.

### TC-P1-04-029: Invalid privacy values are rejected
- Priority: Medium · Type: Validation
- Ref: task Scope `UpdatePrivacyRequest`
- Preconditions: as TC-P1-04-028.
- Steps:
  1. PATCH `/settings/privacy` with `profile_visibility: 'friends'`, `show_clan: 'maybe'`, and `searchable` left out (other fields valid); log `await r.text()`.
- Expected: 422 with "The selected who can see your profile is invalid.", "The show clan field must be true or false." and "The searchable field is required."; the row is unchanged.

### TC-P1-04-030: Privacy writes by account status
- Priority: High · Type: Authorization
- Ref: task Acceptance "restricted accounts can, suspended and pending-deletion accounts cannot"; specs/04 §3
- Preconditions: signed in as `test_user` on `/settings/privacy`; status changed in Adminer between rounds (reset to `active` at the end).
- Steps:
  1. `status = 'restricted'`: change a toggle and save.
  2. `email_verified_at = NULL` (status active): change a toggle and save; then restore `email_verified_at = now()`.
  3. `status = 'suspended'`: without reloading, change a toggle and save.
  4. `status = 'pending_deletion', deletion_requested_at = now(), deletion_previous_status = 'active'`: save again.
- Expected: Steps 1–2 save ("Saved."). Step 3: 403 page "Your account is suspended" / "Changes are off until the suspension ends."; step 4: "Your account is scheduled for deletion" / "Changes are off while the deletion is pending."; the row is unchanged in both.

### TC-P1-04-031: Guests cannot open privacy settings
- Priority: Medium · Type: Authorization
- Ref: task Scope (`auth`)
- Preconditions: signed out.
- Steps:
  1. Open `/settings/privacy`.
- Expected: Redirect to `/login`.

## UI states

### TC-P1-04-032: Privacy form saving and error focus
- Priority: Medium · Type: UI state
- Ref: task Acceptance "States"
- Preconditions: signed in as `test_user`; Network throttling "Slow 3G".
- Steps:
  1. Change the visibility and save; watch the button.
  2. Use the radio group and toggles with the keyboard only (Tab, arrows, Space).
- Expected: Step 1: the button shows a loading state, then "Saved." Step 2: arrow keys move between the radio options, Space flips each toggle, focus is visible throughout.

### TC-P1-04-033: Profile loading skeleton
- Priority: Low · Type: UI state
- Ref: task Scope "A skeleton covers loading"
- Preconditions: signed in as `test_user`; `test_moderator` public; Network throttling "Slow 3G".
- Steps:
  1. Open `/u/test_moderator`, then choose "Your profile" from the avatar menu.
- Expected: While the visit loads, the skeleton shows (avatar circle, two text lines, three stat placeholders, a card) and screen readers get "Loading profile"; then `/u/test_user` renders.

### TC-P1-04-034: 404 page content
- Priority: Medium · Type: UI state
- Ref: task Decisions 6
- Preconditions: none.
- Steps:
  1. As a guest open `/u/nobody_here`; click the button.
- Expected: "We couldn't find that profile" with "Check the username in the link, or head back to the home page." and a "Go to the home page" button that opens `/`.

### TC-P1-04-035: Layout at 375 px and desktop
- Priority: Medium · Type: UI state
- Ref: task Acceptance "States"; Decisions 11
- Preconditions: `test_user` with every field filled and a 300-character bio.
- Steps:
  1. At 375 × 812 open `/u/test_user` (as owner) and `/settings/privacy`.
  2. Repeat at 1280 px.
- Expected: At 375 px: no horizontal scroll; the cover band stacks avatar above the name; a long display name wraps; the three stats stay in one row; social links are at least 44 px tall; the privacy radio rows and toggles fit the width. At 1280 px: avatar and name sit side by side with the owner buttons on the right; the privacy form sits beside the settings sub-nav.
