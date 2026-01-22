# Pre-Production Deployment Checklist

## ✅ Refactoring Completed (Jan 22, 2026)

### High-Priority Items (All Done)
- [x] Created missing `contact_enrichment` table migration
- [x] Fixed repository encapsulation (private $db)
- [x] Created repository interfaces
- [x] Extracted HTTP client abstraction
- [x] Added configuration validation
- [x] Created JSON response helper

---

## Pre-Deployment Steps

### 1. Environment Configuration
```bash
# Review .env file
nano .env

# Ensure these are set:
- DB_HOST
- DB_NAME
- DB_USER
- DB_PASS (can be empty for XAMPP)
- INTERNAL_DOMAIN
- INTERNAL_EMAIL
- MONDAY_API_KEY (if using Monday.com)
- MONDAY_BOARD_ID (if using Monday.com)
- PERPLEXITY_API_KEY (if using enrichment)
```

### 2. Database Setup
```bash
# Run migrations
php migrations/migrate.php

# Verify tables exist
mysql -u root -e "USE email_tracking_local; SHOW TABLES;"

# Should see:
# - threads
# - emails
# - contact_enrichment
# - monday_sync
# - migrations
```

### 3. Permissions Check
```bash
# Ensure logs directory is writable
chmod 755 logs/
touch logs/app.log
chmod 644 logs/app.log

# Verify web server can write
ls -la logs/
```

### 4. Test Endpoints
```bash
# Test health check
curl http://localhost/networkemailtracking/health

# Test dashboard (should return JSON with stats)
curl http://localhost/networkemailtracking/api/dashboard | jq '.stats'

# Test webhook (simulate incoming email)
# Use tests/test-webhook-with-monday.php
php tests/test-webhook-with-monday.php
```

### 5. Verify Integrations

#### Monday.com
```bash
# Test Monday API connection
php tests/test-monday-api.php

# Test sync functionality
php tests/test-monday-sync.php
```

#### Perplexity AI
```bash
# Test enrichment
php tests/test-enrichment.php
```

---

## Production Deployment Checklist

### Before Pushing to Production

- [ ] Backup database
- [ ] Review all .env variables
- [ ] Verify API keys are production keys (not dev/test)
- [ ] Check log file permissions
- [ ] Test all endpoints in staging
- [ ] Review error logs for warnings
- [ ] Ensure HTTPS is configured (for webhook security)

### Post-Deployment Verification

- [ ] Health check returns 200 OK
- [ ] Dashboard loads and shows correct data
- [ ] Send test webhook (from Power Automate or test script)
- [ ] Verify email is saved to database
- [ ] Check enrichment runs automatically
- [ ] Verify Monday.com sync works
- [ ] Review logs for errors

### Monitoring Setup

- [ ] Set up log rotation for `logs/app.log`
- [ ] Configure error alerting
- [ ] Monitor webhook response times
- [ ] Monitor Monday.com API rate limits
- [ ] Monitor Perplexity API usage

---

## Rollback Plan

If issues occur in production:

1. **Database**: Run down migration if needed
   ```bash
   # Not recommended unless absolutely necessary
   # Contact enrichment table can be dropped manually if needed
   ```

2. **Code**: Revert to previous commit
   ```bash
   git log --oneline
   git revert <commit-hash>
   ```

3. **Config**: Restore previous .env file from backup

---

## Known Limitations

1. **No retry logic** - API failures are immediate (on roadmap)
2. **No rate limiting** - Webhook endpoint is open (consider adding)
3. **No authentication** - API endpoints are public (secure via firewall)
4. **Synchronous processing** - Enrichment/sync happens during webhook (consider background jobs)

---

## Support Contacts

- **Application Issues**: Check logs/app.log
- **Database Issues**: Verify migrations ran successfully
- **API Issues**: Check Perplexity/Monday.com API keys and rate limits
- **Webhook Issues**: Verify Power Automate is sending correct format

---

## Testing Strategy

### Manual Testing
1. Send real email to monitored address
2. Verify appears in dashboard within 1 minute
3. Check enrichment data populated
4. Verify Monday.com item created

### Load Testing
- Monitor performance with multiple concurrent webhooks
- Check database query performance
- Monitor API rate limits

---

## Success Metrics

Track these after deployment:
- Webhook success rate (target: >99%)
- Average enrichment accuracy (manual review sample)
- Monday.com sync success rate (target: >95%)
- Average webhook processing time (target: <2 seconds)
- API error rates (target: <1%)

---

## Next Steps (Future Enhancements)

1. Add retry logic for API calls
2. Implement background job queue
3. Add webhook authentication
4. Create admin dashboard for monitoring
5. Add PHPUnit test suite
6. Implement caching layer for frequent queries
