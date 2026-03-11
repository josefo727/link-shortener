# Next Steps

## Phase 2 Remaining

### Usage Analytics
- Define analytics data model (clicks over time, referrers, user agents)
- Create migration for analytics table
- Track click events asynchronously via queued jobs
- Add analytics dashboard widget to Filament panel

---

## Phase 3: Advanced Features

### Custom Slugs/Codes
- Allow users to specify custom codes when creating short URLs
- Validate custom codes (length, characters, uniqueness)
- Update CreateShortUrlAction and API endpoint

### Link Expiration Automation
- Scheduled command to mark expired URLs as `expired` status
- Consider 302 redirects for expiring URLs instead of 301

### Click Tracking with Geolocation
- Store IP, user agent, referrer per click
- IP-based geolocation (MaxMind GeoLite2 or similar)
- Device type detection

### Bulk URL Shortening
- API endpoint for batch creation
- Filament import action (CSV/Excel)
- Queue processing for large batches

---

## Technical Debt

- Update `PROGRESS.md` session notes as development continues
- Consider adding a ViewShortUrl page to Filament resource
- Evaluate adding API endpoint for public QR code generation

---
