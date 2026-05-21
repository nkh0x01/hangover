# Rollback Plan

> What to do when a deploy goes wrong. Designed to be skim-readable
> during a P1 incident — the steps are numbered and copy-pasteable.
>
> The atomic-releases layout from `vps-bring-up.md` § "Filesystem
> layout" + `deployment-checklist.md` makes most rollbacks a
> single-second symlink flip. The hard cases are migrations and
> shared-state corruption — covered below.

## TL;DR

```bash
# Find the previous release:
ls -1dt /var/www/hangover/releases/*/ | head -3
# Typical output:
#   /var/www/hangover/releases/20260513184500/  ← BAD (current)
#   /var/www/hangover/releases/20260512112300/  ← LAST KNOWN GOOD
#   /var/www/hangover/releases/20260511090700/

# Flip:
ln -sfn /var/www/hangover/releases/20260512112300 /var/www/hangover/current

# Restart workers + Reverb:
sudo systemctl reload php8.3-fpm
sudo supervisorctl restart hangover-horizon hangover-reverb

# Smoke:
curl -fsS https://ride.365sakartvelo.com/api/v1/health
```

Sub-second user impact if the rollback target was healthy at deploy
time.

## Decision tree

```
Bad deploy?
   │
   ├── Did this release change the DB schema?
   │      │
   │      ├── NO  → simple rollback (Path A)
   │      │
   │      └── YES → check the change shape
   │             │
   │             ├── Expand-only (added columns/tables, no drops)
   │             │      → simple rollback (Path A) — new columns
   │             │        sit unused
   │             │
   │             └── Contract (dropped columns, renamed)
   │                    → cannot simple-rollback (Path B —
   │                       restore from backup OR roll forward)
   │
   └── Is the failure local to one feature?
          │
          └── Consider a hot-fix-forward instead of rollback
              (Path C)
```

## Path A — Simple atomic rollback (no migration changes)

This is the 95% case during pilot.

1. **Identify** the last known good release directory:
   ```bash
   ls -1dt /var/www/hangover/releases/*/ | head -5
   ```
   Cross-reference with `/var/log/hangover-deploys.log` to find the
   release that worked.

2. **Flip** the symlink:
   ```bash
   ln -sfn /var/www/hangover/releases/<KNOWN-GOOD> \
           /var/www/hangover/current
   ```
   This is atomic — there is no moment when two requests see
   inconsistent code.

3. **Refresh caches** on the now-active old release:
   ```bash
   cd /var/www/hangover/current/backend
   php artisan config:clear
   php artisan route:clear
   php artisan view:clear
   php artisan event:clear
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   php artisan event:cache
   ```

4. **Reload PHP-FPM** to drop opcache:
   ```bash
   sudo systemctl reload php8.3-fpm
   ```

5. **Restart workers + Reverb** against the old release:
   ```bash
   sudo supervisorctl restart hangover-horizon hangover-reverb
   ```

6. **Smoke-test**:
   ```bash
   curl -fsS https://ride.365sakartvelo.com/api/v1/health
   curl -s -o /dev/null -w "%{http_code}\n" https://ride.365sakartvelo.com/admin/login
   ```

7. **Log it**:
   ```bash
   echo "$(date -u +%FT%TZ) $(whoami) rolled back to $(readlink /var/www/hangover/current)" \
     >> /var/log/hangover-deploys.log
   ```

8. **Post-mortem** within 24 h — what changed in the bad release,
   why it slipped through `deployment-checklist.md` § "Post-deploy
   verification", which check needs to be added.

Total time: < 2 minutes.

## Path B — Contract-migration rollback (requires DB restore)

When a deploy ran a migration that dropped columns, renamed tables,
or changed enums, the old code can't read the new schema. Three
choices:

### B.1 — Roll forward with a hot-fix (preferred)

If the bad deploy is broken but the schema is fine, write a
hot-fix on the new schema rather than reverting. Faster + cleaner
than a DB restore. See "Path C".

### B.2 — Reverse migration

If the migration was correctly reversible (`down()` is defined and
non-destructive), run it on the bad release:

```bash
cd /var/www/hangover/releases/<BAD-RELEASE>/backend
php artisan migrate:rollback --step=1 --force
```

…then flip the symlink (Path A). Caveats:

- `--step=1` rolls only the latest batch. If the bad release did
  multiple migrations, use the right number.
- Data written into the new schema between deploy and rollback
  is lost unless the `down()` migration preserves it (it usually
  doesn't).
- This works only if every migration in the bad release is in the
  same batch. Mixed-batch rollback is fragile — go to B.3.

### B.3 — Restore from backup

Last resort. The daily backup from
`vps-bring-up.md` § "Backups" lives in `/var/backups/hangover/`:

```bash
# Stop traffic to prevent inconsistent state:
sudo nginx -s stop  # OR `php artisan down --secret=<token>`

# Stop workers — must NOT run against an old schema:
sudo supervisorctl stop hangover-horizon hangover-reverb

# Restore the database:
gunzip < /var/backups/hangover/hangover-<TS>.sql.gz \
  | mysql -u hangover -p hangover

# Flip back to the pre-bad release:
ln -sfn /var/www/hangover/releases/<KNOWN-GOOD> \
        /var/www/hangover/current

# Restart everything:
sudo systemctl start nginx
sudo supervisorctl start hangover-horizon hangover-reverb
```

Then communicate the data loss:
- Customer-side: any rides between backup time and restore time are
  gone. Ops messages the affected riders, refunds via wallet credit.
- Driver-side: similar — payouts re-computed from before-bad-deploy
  state.

Total time: ≥ 15 minutes. Avoid this path by writing migrations
defensively (expand/contract, never destructive in a single release).

## Path C — Hot-fix forward

Sometimes the right answer is to fix forward, not roll back. When:

- The bad release has the wrong copy on one screen.
- The bad release dropped a non-critical feature.
- The bad release has a config drift fixed by toggling an env var.

Steps:

1. Identify the one-line fix.
2. Branch from the bad release: `git checkout -b hotfix/<short> <bad-tag>`.
3. Patch + push.
4. Tag `pilot-vX.Y.Z+hotfix.N`.
5. Run `./deploy.sh pilot-vX.Y.Z+hotfix.N`.
6. Post-mortem within 24 h.

Faster than a rollback when the fix is obvious.

## Special cases

### Bad Reverb broadcast schema

Reverb broadcasts versioned events (`'v' => 1` in the broadcast
payload). If a deploy ships a new event version that older mobile
clients don't understand, mobile clients ignore it (Phase 1.5 ship)
— no rollback needed. **But** if a deploy *changes* an existing
event shape without bumping the version, old mobile clients
crash. The fix is forward — never change shape without bumping.

### Bad migration is already running

`php artisan migrate` is mostly atomic per migration file but not
across files. If one of three migrations succeeded and the next
failed:

```bash
php artisan migrate:status
# Shows which ran. The failed one needs manual fix.
```

For pilot scale this is rare — review every migration via
`docs/architecture/02-data-model.md` before merging.

### Redis flushed (cache or queue)

If a deploy or operator accidentally flushed Redis:
- Queues lost: jobs not yet processed are gone. Most are
  retry-able by design (heartbeat, push). The lost work is
  bounded.
- Cache lost: the next request rebuilds it. Slow for a few seconds.
- GEOSEARCH index lost: drivers re-register their position on the
  next heartbeat (< 30 s). Until then dispatch returns
  no-drivers.
- Sessions lost: admin users have to re-login.

No rollback needed — wait 60 s for the system to heal itself.

### Reverb wedged

Reverb is stateless. `sudo supervisorctl restart hangover-reverb`
is always safe. If WebSocket connections are stuck:

```bash
sudo supervisorctl restart hangover-reverb
# Mobile clients reconnect within 5 s thanks to the
# ReconnectScheduler shipped in Phase 2.0.
```

## When to bring the platform fully down

Hard down — `php artisan down --secret=<token>` — is appropriate
during:

- A B.3 backup-restore in progress (already noted above).
- A storage corruption requiring manual file-level recovery.
- A confirmed data breach where every active session must be
  invalidated (no — actually that's `auth.revoke-all`, not a full
  outage; but if it happens before that route exists, full-down
  works).

Procedure:

```bash
# Up:
cd /var/www/hangover/current/backend
php artisan up

# Down:
php artisan down --secret=tbilisi-pilot-only --render="errors::503"
# Bypass URL for ops: https://ride.365sakartvelo.com/?secret=tbilisi-pilot-only
```

The mobile clients see a generic "Service unavailable, please try
again" copy + auto-retry every 30 s.

## Comms during rollback

Templates for the ops Slack channel:

### Soft (Path A or C)

> "Rolling back the 18:45 deploy due to <one-line cause>. ETA back
> on the new code: <eta>. Customers not affected — pilot dashboard
> shows green."

### Hard (Path B.3)

> "🟥 Platform paused for emergency rollback. Cause: <one-line>.
> Active rides may have been interrupted. ETA back: 15 min. SRE +
> Ops lead on it."

Plus SMS to riders + drivers if the down window exceeds 5 min:

> "Hangover is briefly paused while we investigate an issue. Your
> active ride is safe — the driver will complete the trip; payment
> finalises when service is back. ETA: <Y> minutes."

## Drill it

Quarterly: practise a deliberate rollback in staging during a
quiet hour. Time it. If you can't do Path A in under 5 minutes
unattended, the procedure has rotted — re-read this doc.

## What we'll never do during pilot

- Restore from a backup older than 24 h. The data delta is too big
  — escalate to "stop pilot, do post-mortem, plan a fresh cohort".
- Force-push to the production deploy branch.
- Skip `sudo nginx -t` before reload.
- Run `migrate:fresh --seed` in production. Ever.
- Use destructive operations (`git reset --hard`, `rm -rf releases/*`)
  without a backup confirmation.
