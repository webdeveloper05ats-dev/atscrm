# Backup & Health Module - Product PRD

## 1. Product Summary

### Product name
ATS CRM - Backup & Health Monitor

### Positioning
An operations reliability module for training institutes/education businesses to prevent data loss, detect system issues early, and guide quick recovery.

### Core promise
"Know system risk in minutes, not after damage."

---

## 2. Business Goals

1. Reduce production downtime caused by DB/storage/config issues.
2. Reduce data-loss incidents with disciplined backup checks.
3. Make non-technical admins capable of first-level troubleshooting.
4. Create a sellable add-on tier for CRM product revenue.

---

## 3. Target Customers

1. Small to mid-size institute chains using ATS CRM.
2. Franchise branches with centralized admin oversight.
3. Operations heads who need quick status, not raw server logs.
4. CRM resellers who need reliability features as upsell.

---

## 4. Personas

1. Super Admin: needs one-screen health status + immediate actions.
2. Branch Admin: needs clear alerts and what to escalate.
3. Support Engineer: needs root-cause hints and remediation history.
4. Founder/Director: needs monthly reliability report and SLA evidence.

---

## 5. Scope

### In scope (v1)
1. Manual health run.
2. Backup status and history.
3. Rule-based checks with severity.
4. Recommended actions per failed check.
5. Run history persistence and run count.

### Out of scope (v1)
1. Fully automatic DB schema auto-fix.
2. External SIEM integrations.
3. Cross-cloud infra observability.

---

## 6. Feature Set

### A. Health Check Engine
1. DB connectivity check.
2. Core table existence check.
3. Core index presence check.
4. Uploads directory readability check.
5. Severity: pass/warn/fail.

### B. Guided Troubleshooting
1. Recommended Action column per row.
2. Code/config level hints for fail/warn.
3. Sequential "testing" UI for transparency.

### C. Backup Reliability
1. Manual database backup generation.
2. Backup history listing with download.
3. Uploads export.
4. Basic cleanup policy for old backups.

### D. Reporting UX
1. KPI cards (total/pass/warn/fail).
2. Last run timestamp.
3. Checks in last run + total run count.
4. Persistent last health result after refresh.

---

## 7. Advanced Checks Roadmap (Productization)

### v1.1 (high value, low risk)
1. Last backup age alert.
2. Backup folder writable check.
3. Disk free-space check.
4. Leads without follow-up > SLA threshold.
5. Payment mismatch sanity check.

### v1.2
1. Orphan data checks (broken references).
2. Duplicate contact anomaly checks.
3. Inactive users with elevated access check.
4. Old pending alerts trend graph.

### v2.0 (enterprise)
1. Scheduled auto checks (hourly/daily).
2. Alerting via email/WhatsApp.
3. Multi-branch health dashboard.
4. SLA monthly export (PDF/CSV/API).
5. Approval-based safe auto-remediation.

---

## 8. Plan & Pricing Strategy

### Starter (included / base CRM)
1. Manual health check.
2. Backup history.
3. Basic recommended actions.

### Growth (paid add-on)
1. Scheduled checks.
2. Email alerts.
3. 30-day run history + trends.
4. Branch-wise risk summary.

### Enterprise (premium)
1. Multi-tenant central console.
2. SLA reports + API export.
3. Role-based approval workflow.
4. Dedicated support + priority SLA.

### Pricing metric
Per branch per month (recommended), with volume discount for multi-branch clients.

---

## 9. GTM (Go-To-Market) Pack

1. Demo script:
   - Show normal pass state.
   - Simulate one fail.
   - Show recommendation and recovery flow.
2. Sales collateral:
   - "Downtime prevention" one-pager.
   - Reliability score screenshot.
   - Before/after incident response time.
3. Commercial assets:
   - Plan comparison sheet.
   - ROI calculator (hours saved, incidents avoided).
4. Support assets:
   - Quick-start SOP.
   - Escalation matrix.
   - FAQ for common failures.

---

## 10. Success Metrics (KPIs)

1. Mean time to detect (MTTD) for critical issues.
2. Mean time to resolve (MTTR) after first alert.
3. Number of fail states per month per branch.
4. Backup freshness compliance (% within SLA).
5. Add-on conversion rate (Starter -> Growth/Enterprise).

---

## 11. Non-Functional Requirements

1. Health check runtime should remain predictable for mid-size DB.
2. UI must remain usable on low-resolution branch systems.
3. All outputs must be understandable by non-technical users.
4. No destructive auto-fix without explicit confirmation + backup.
5. Audit trail for every health run and fix action.

---

## 12. Risks & Mitigation

1. False positives from metadata checks.
   - Mitigation: configurable thresholds.
2. Performance overhead on large DBs.
   - Mitigation: staged checks and timeout safeguards.
3. Unsafe remediation pressure.
   - Mitigation: "safe only" auto-fix + approval gate.
4. Adoption drop if UI is too technical.
   - Mitigation: plain-English recommendations and status copy.

---

## 13. Implementation Roadmap (Execution)

### Sprint 1
1. Stabilize current module UX + persistence.
2. Add recommended action engine.
3. Add run count and report retention.

### Sprint 2
1. Add backup age/disk/writable checks.
2. Add branch-level summary cards.
3. Add exportable health report.

### Sprint 3
1. Add scheduler and notifications.
2. Add alert preferences and escalation channels.
3. Add plan gating (feature flags by license tier).

---

## 14. Launch Checklist

1. Security review complete.
2. Backup/restore dry-run test complete.
3. Load test on real-like dataset complete.
4. Sales demo dataset prepared.
5. Plan/pricing and legal terms finalized.
6. Support playbook and ownership finalized.

---

## 15. Immediate Next Steps

1. Approve plan names and pricing model.
2. Approve v1.1 check list (5 checks).
3. Implement feature flags for Starter/Growth/Enterprise.
4. Prepare demo script and screenshot deck.

