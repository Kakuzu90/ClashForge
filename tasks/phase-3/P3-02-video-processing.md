---
id: P3-02
title: Process base replay videos on the media worker (probe, transcode, poster) and open base_video uploads
phase: 3
status: done
depends_on: [P0-05]
---

# Video processing

## Spec refs
- Core: specs/10 §6 (strategy, transcode, poster, output cap, timeout, resource guard, `MediaProcessor` swap), §3 (pipeline steps c–k for video), §4 (video column: mp4, 100 MB, 60 s, codecs, metadata stripped, re-mux as the polyglot defence), §10 (failure modes); specs/20 §1–2 (`media` queue, 900 s, `ProcessMediaJob`, poster in the same job), §6 (media processing p95 > 180 s)
- Plus: specs/03 NFR-PERF-9 (`media` queue wait < 5 min); specs/07 `media` (`duration_seconds`), `media_variants` (`poster`, `video_720p`); specs/11 Injection (ffmpeg / ffprobe through `Process` with an argument array, app-generated paths only); specs/24 R6, A11
- FR: FR-MEDIA-2, FR-MEDIA-4, FR-MEDIA-6, FR-MEDIA-7
- Edge cases: specs/23 §4 (59 s of black frames passes; no audio stream → video-only output; extra audio tracks and subtitles dropped), §3 (video done after the base was deleted → output discarded)

## Scope
- **Domain** (Media):
  - `VideoProcessor implements MediaProcessor`:
    - magic bytes must be `video/mp4`, anything else is quarantined;
    - `ffprobe` (JSON) runs before any transcode: container, first video stream, first audio stream, duration, resolution and rotation;
    - checks the duration (≤ 60 s, rejected, never truncated), the codecs and the input resolution (Q1, Q2);
    - transcodes to h264 `veryfast` CRF 26, scale down only, aac 96 kbps from the first audio track only, everything else dropped, metadata stripped, `+faststart`; over 40 MB → re-run at CRF 30; still over → fail;
    - takes a WebP poster frame at 10 % of the duration.
  - The `MediaProcessor` binding routes by collection kind to `ImageProcessor` or `VideoProcessor`.
  - `ProcessedMedia` gains `durationSeconds`; a variant can be a local file, not only bytes, so a 40 MB mp4 is streamed to storage.
  - New `MediaFailureReason` cases with video copy: too long, unsupported codec, unreadable video, resolution too large, output too large. Existing image labels stay as they are.
- **ffmpeg runner** (`Support`): `Process` with an argument array only, `nice -n 10`, ffmpeg `-timelimit`, a process timeout below the job's 900 s, and work only inside the job's temp dir.
- **Uploads:** `base_video` gets `accepts_uploads: true`; the intent allows `video/mp4` with `.mp4` only; the stored quarantine extension comes from the declared type (Q3: who may upload).
- **Metrics:** `media.processing_started_at`, set when a run claims the row; the System Health "Media processing p95" row over the last 24 h against 180 s (Q4).
- **Config** (`media.video`): `mimes`, `extensions`, `types_label`, `max_duration` 60, `max_input_width/height`, `output_short_side` (720), `crf` 26, `crf_retry` 30, `max_output_bytes` 40 MB, `audio_bitrate` 96k, `poster_at` 0.1, `ffmpeg_timelimit`, `nice` 10, plus the binary paths.

## Out of scope
- The composer's video dropzone and `POST /bases` (P3-08), the player and the "processing video" card state (P3-03 / P3-08).
- Cloudflare Stream or a hosted transcoder (the migration trigger in specs/10 §6), HLS or several resolutions.
- Accepting `.mov` or other containers.

## Acceptance criteria
- Functional: an mp4 within the limits ends `ready` with `video_720p` and `poster` variants, width, height and `duration_seconds`; a base waiting on it publishes through `PublishWhenMediaReady` (FR-BASE-5).
- Authorization: intents as today (auth, verified email, active, 30 / hour), plus Q3.
- Edge cases:
  - over 60 s, an unknown codec, or a non-mp4 behind an `.mp4` name: refused, the last one quarantined;
  - no audio and several audio tracks, as specs/23 §4;
  - a deleted or soft-deleted parent: the output is discarded (the existing locked `processing` check);
  - a timeout: retried once, then `failed` as `processing_error`.
- States: the status endpoint reports the video failure copy; no new UI.

## Tests
- Unit: building the ffprobe parse and the ffmpeg arguments (argument array, no shell string, scale filter, CRF retry); duration, codec and resolution rules.
- Feature, against tiny generated fixtures (`ffmpeg -f lavfi` in the test setup, skipped if ffmpeg is missing):
  - happy path to `ready` with variants;
  - too long; no audio; two audio tracks plus a subtitle;
  - a non-mp4 with an `.mp4` name is quarantined;
  - output over the cap after the retry fails;
  - a deleted parent is discarded;
  - a base publishes on the video's `MediaReady`;
  - the intent accepts `video/mp4` up to 100 MB and refuses other types.
- Security: no user string reaches the command line (the filename and the ULID path); the metadata (title, GPS) is gone from the output.

## Notes

### Open questions
Resolved by the owner, 2026-10-07 (all as recommended); specs synced at implement → Finish.
1. **Output resolution.** FR-MEDIA-4 and specs/10 §4 say "≤1080p after processing", while specs/10 §3 and §6 and the variant name say ≤720p. Recommended: **720p**, as the short side ≤ 720, scaled down only, even dimensions. That suits a progressive clip of 60 s or less and the 40 MB cap; I would correct FR-MEDIA-4 and §4.
2. **Input codecs and size.** FR-MEDIA-4 says "mp4 (h264/aac) only", while specs/10 §4 accepts hevc video and mp3 audio. Recommended:
   - accept h264 or hevc video, with aac, mp3 or no audio, in an mp4 container (iPhone recordings are often hevc);
   - refuse input over 3840 × 2160 before transcoding (phone screen recordings are often wider than 1920);
   - correct FR-MEDIA-4 and §4 to match.
3. **Who may upload a video** (specs/24 R6, "verified-only video if needed"). Today any active user with a verified email gets 30 intents an hour. That could be 30 transcodes queued on the single CPU-capped worker. Recommended:
   - `base_video` intents need a verified CoC account (`users.verified_accounts_count > 0`), the same bar as publishing, so no CPU goes on videos that can never be attached;
   - a separate `media.rate_limits.video_intents_per_day` (10).
4. **The p95 row.** specs/20 §6 left "media processing p95" off System Health until `media` records a start time "with P3-02". Recommended: add `processing_started_at` and the row here. It is one column and one aggregate; the alternative is a follow-up task.

### Decisions and divergences (implement, 2026-10-07)
1. **Resolution and codecs** (Q1, Q2): the output's short side is at most `media.video.output_short_side` (720), scaled down only, with even sides. A 2400 × 1080 phone recording becomes 1600 × 720, and a portrait one stays upright. The input may be h264 or hevc, with aac, mp3 or no audio, up to 3840 × 2160 in either orientation. synced → specs/02 FR-MEDIA-4, specs/10 §4, specs/06 §5.
2. **Real type** (specs/10 §4 "Malware posture"): the worker accepts the ISO media family that one demuxer reads (`video/mp4`, `video/x-m4v`, `video/quicktime`, `media.video.real_mimes`). An iPhone file renamed `.mp4` is benign, so quarantining it would flag an honest uploader. Anything else behind an `.mp4` name (an image, a playlist, a matroska file) is quarantined. The intent still takes `video/mp4` and `.mp4` only. synced → specs/10 §3 step d, §4, specs/07 `media`, specs/23 §4.
3. **No script-marker scan for video:** across 100 MB of compressed bytes the markers would match by chance. The re-encode is the control (specs/10 §4 "Polyglot defence"), and a test shows a PHP payload appended after the container does not reach the rendition. synced → specs/10 §4 Malware posture.
4. **ffmpeg hardening** (specs/11 Injection):
   - inputs are read with `-f mov -protocol_whitelist file`, so a disguised playlist or reference cannot fetch anything;
   - output stops at `max_duration + 1` s even if the container lied about its length;
   - `nice -n 10`, `-timelimit` (CPU seconds), and per-process timeouts (probe 30 s, transcode 360 s, poster 60 s) whose worst run (2 probes, 2 transcodes, 1 poster, 840 s) stays inside the job's 900 s; a test holds that. `cpulimit` is not used: the media container already has a CPU cap.
   - The input limits are `max_long_side` / `max_short_side` (3840 / 2160, either orientation), not the `max_input_width/height` Scope named.
   synced → specs/10 §6, specs/11 Injection.
   - A failed ffmpeg run throws and is retried by the job (then `processing_error`), since it can be the worker as much as the file. Only an unreadable probe is a deterministic `undecodable`.
5. **Streams:** the first video stream that is not cover art, and the first audio track. Audio becomes aac at 96 kbps with at most 2 channels. Subtitles, data, chapters and all metadata are dropped (specs/23 §4). The poster is a WebP frame at 10 % of the output's duration, the same size as the video. `media.width/height` are the output size, and `duration_seconds` is the output length. synced → specs/10 §6, specs/07 `media`, specs/23 §4.
6. **Failure copy:** `MediaFailureReason::message(MediaKind)` gives video wording for `undecodable`, `dimensions_too_large` and `processing_error`. New cases: `duration_too_long`, `unsupported_codec`, `output_too_large`. `label()` keeps the image wording. The intent's type error now reads per kind, from `media.{kind}.unsupported_message`. synced → specs/10 §3 "Status endpoint", §4.
7. **Who may upload a video** (Q3):
   - `MediaPolicy` requires `hasVerifiedCocAccount()`, new on `HasAccountStanding`, for `base_video` at intent and at complete.
   - `media.rate_limits.video_intents_per_day` (10) is taken in `UploadIntentService` before the row exists, as a field error on `collection`. An over-limit try does not use a slot, and the slot is given back if presigning fails.
   synced → specs/04 §1, §4 (`upload-video`), specs/10 §3, §8, specs/24 R6.
8. **p95** (Q4): `media.processing_started_at` is set by each claim. `MediaProcessingStats` (Media, public) ranks the window in PHP (nearest rank), the same on both databases. System Health shows it under the queue table in the `queues` deferred group, with `media.health.*` for the window (24 h) and the alert (180 s). A partial index `media (processed_at) WHERE processing_started_at IS NOT NULL` serves it. synced → specs/20 §6, specs/07 `media`, specs/05 §1 Media row, specs/19 config.
9. **Renditions as files:** `ProcessedVariant::fromFile` streams a transcoded mp4 to storage with `writeStream`, so it is never read into memory. The write happens under the media row lock, as for images (specs/10 §9: the lock is held until the variant rows are saved), so a slow storage write holds that one row for as long. synced → specs/10 §3 step h.

### Review fixes (verify, 2026-10-07)
- antislop audit-046: no findings.
- Spec (medium): a container claiming a shorter length than its streams was published cut at 61 s. The output is now probed and held to the duration limit too (`VideoRules::checkDuration`), so it is refused as `duration_too_long`. Tested.
- Spec (medium): Decision 2 (accepting `video/quicktime` and `video/x-m4v` bytes behind an `.mp4` name) goes beyond "a non-mp4 behind an `.mp4` name: quarantined". Confirmed by the owner, 2026-10-07: keep it.
- Spec + security (low/medium): the step timeouts added to 1140 s, over the job's 900 s. Split into `probe_timeout` / `transcode_timeout` / `poster_timeout` (840 s in the worst run), tested.
- Spec (low): an unreadable probe of our own output was reported as the uploader's `undecodable`; it is now a worker fault, retried. The ffmpeg-failure path (retry, then `processing_error` with video wording, original kept) is tested. `BaseLayoutPolicy` uses `hasVerifiedCocAccount()`, so the publish bar and the video bar cannot drift.
- Spec (low): stream-level metadata. `-map_metadata -1` already drops stream titles, languages and handler names; a security test now proves it.
- Spec (low): the day limit is a 24 h window from its first intent, not rolling; the config comment says so.
- Security (medium): ffprobe opened every track's decoder before the codec allowlist ran. The probe now reads headers only (`-nofind_stream_info`), codecs are checked first, and ffmpeg may open only `media.video.decoders` (`output_decoders` for the poster); a track in another codec is dropped without failing the upload. Tested.
- Security (medium): one uploader could hold the single media worker. Now:
  - input over `max_frame_rate` (120) is refused as the new `frame_rate_too_high`;
  - the output is capped at `output_max_fps` (60);
  - `-fs` stops a pass at the size cap;
  - one replay video in flight per user: a second intent, or completing a second pending one, waits with "Your last video is still processing." (tested).
- Security (low): `-enable_drefs 0 -use_absolute_path 0` are now explicit instead of package defaults; ffprobe's error output is truncated to 500 characters in logs.

### Follow-ups
- `useUpload` polls for about 3 minutes by default (`maxPolls` 40). The composer (P3-08) should pass a longer poll for `base_video`, or show "still processing, you can publish now" (a base waits in `processing`, FR-BASE-5).
