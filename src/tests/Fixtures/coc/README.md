# CoC API fixtures

Every file here is **synthetic** (`"_fixture": "synthetic"`), written from the documented API shape
because no developer key exists yet (P2-01, open question 1). Replace them with recorded responses
once a key is available, keeping the file names; the contract tests in `tests/Contract/Coc` must
pass on both.

- `players/{TAG}.json`, `clans/{TAG}.json`: what `FakeCocApiClient` serves (tag without `#`).
  - `2PQ8GRJC` full player in a clan; `LQ2RJ9P0` no clan or league; `YC8V2QG9` Town Hall 18,
    unknown units and an unknown field; `GRJ0P8UV` most fields missing.
- `responses/`: bodies for `Http::fake` tests (verifytoken, error reasons, malformed players).

Never put a real player's data or a real token here.
