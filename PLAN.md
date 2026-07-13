# Difftor 2.0 — Phased Release Plan

Each phase is independently shippable. Do not skip phases — later phases assume earlier changes.

Legend: **BREAKING** = requires major-version bump. **INTERNAL** = no public API impact.

---

## Phase 1 — Security & Correctness (ship-blockers)

DONE

---

## Phase 2 — Core UX & Integration API

**Goal:** Make difftor usable in CI and scriptable. Ship the flags external users have asked for.

| # | Change | Details |
|---|---|---|
| 2.4 | **Stats header in HTML.** Top of doc: file count, +additions/−deletions summary. Interpolate source names into `<title>` and `<h1>` (fallback to "Diff Comparison" only when both are directories with unhelpful names). | [src/Utils/HtmlUtils.php:93](src/Utils/HtmlUtils.php#L93) |

---

## Phase 3 — Code Quality & Hardening

**Goal:** Kill dead code, add static analysis, cover the untested service layer.

| # | Change | Rationale |
|---|---|---|
| 3.1 | **Simplify `readFileAsUtf8`.** Collapse 4-level fallback ladder to single `mb_convert_encoding($content, 'UTF-8', mb_detect_encoding($content, [...], true) ?: 'ISO-8859-1')`. Delete iconv branches. | Violates project simplicity rule; no test proves the deeper fallbacks fire. |
| 3.2 | **Trim `generateFileId`.** `md5($file_path)` is already unique; drop the sanitized suffix. Prefix stays for CSS selector safety. | Dead code. |
| 3.4 | **Normalize paths once.** Fix Windows separator drift in `getDirectoryFiles` — return forward-slash relative paths always. | [src/Utils/FileUtils.php:45](src/Utils/FileUtils.php#L45) |
| 3.6 | **Tighten Symfony Console range.** Drop 5.4, drop 6.0. Require `^6.4 \|\| ^7.0`. | **BREAKING** — dependency floor. Justified for 2.0. |
| 3.7 | **Integration tests for `DifftorService::generateDiff`.** End-to-end with real fixture directories + zip files. Cover: identical, added, removed, renamed folder, mixed sources. | Only utils are tested currently. |

**Gate:** Integration test suite green. Lint + format + phpunit all pass.

---
