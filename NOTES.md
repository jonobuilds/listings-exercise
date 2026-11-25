# Notes

`php artisan migrate:fresh --seed` gives the demo user a saved search with two alerts. Publish a draft with `PublishListing` in tinker to trigger another.

## Decisions

- **A match is the same search as the listings page.** Both use `ListingCriteria`. Blank means "any", and the limits are inclusive. Alerts ask the reverse question (which searches does this listing match?), so `MatchingConsistencyTest` checks both sides agree. Otherwise someone could be alerted about a listing their own search wouldn't show.
- **Criteria:** type, price range, min bedrooms and region. I chose a range over a max because there's no price sort, so a £400k budget gets buried under £150k flats. I left out bathrooms and price drops; alerting on changes is a different feature, and spammy.
- **Duplicates:** one alert per user per listing, enforced by a unique index rather than a PHP check, so two requests at once can't both get through.
- **Not spamming:** a search needs at least one filter, its region has to exist, and edits to a live listing don't alert.
- **No backfill.** You save from the results you're already looking at, so alerting on those is noise.
- **Trigger:** a model hook fires `ListingWentLive` after commit on any change to Live, so an import alerts the same as `PublishListing`. It's synchronous because there's no queue worker; in production I'd queue it. It's idempotent, so retries are safe.

## Existing code I changed

- The auth stub only set the request resolver, so `auth()->user()` and policies saw a guest. `auth` also ran before it. I fixed both, with a test that doesn't use `actingAs()`.
- I fixed an N+1 on the listings index, with a query-count test.
- The README says "API" in the intro but "no JSON API" under The stack. I went with Inertia only.

## Left out / with more time

A daily email digest (a scheduled job plus `notified_at`), read/unread, editing searches, and a cap on searches per user.

## Where it won't hold up

- Every publish scans all of `saved_searches`, because `IS NULL OR` can't use an index, and it does so inside the request. Bulk `update()` calls skip model events entirely. I'd move this to a queued batch job on `went_live_at`, narrowed by region first.
- `listing_alerts` is never pruned.
- The listings page needs composite indexes and cursor pagination at around 100k rows.
- Region is exact-match free text.
