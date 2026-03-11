# Zero Trust Future TODO

1. Inventory access boundaries.
Document every sensitive route, background job, webhook, OAuth flow, and bank-integration action that touches user or financial data.

2. Classify sensitive actions.
Mark which actions need stronger protection, such as linking bank accounts, changing password, changing email, deleting profile, exporting data, or enabling third-party integrations.

3. Enforce step-up authentication.
Require fresh password confirmation or 2FA challenge for high-risk actions, not just for login.

4. Tighten authorization coverage.
Review every controller, Form Request, job, and service to confirm ownership and policy checks exist for every resource access path.

5. Verify least-privilege defaults.
Check that users only receive the minimum access needed, and that no route or action relies only on frontend visibility rules.

6. Review session trust assumptions.
Evaluate session lifetime, remember-me behavior, re-auth requirements, device/session revocation, and whether sensitive actions should ignore old sessions.

7. Add audit logging for sensitive events.
Track security-relevant actions such as login, failed login, 2FA enable/disable, password change, email change, OAuth link/unlink, and bank connection changes.

8. Add anomaly and abuse detection.
Review rate limits and add detection for unusual login patterns, repeated failed 2FA, repeated OAuth failures, and sensitive action bursts.

9. Strengthen external identity validation.
Confirm Google and GitHub sign-in, email verification, account linking, and recovery flows cannot be abused for account takeover.

10. Review secret and key handling.
Check storage, rotation, and exposure risks for API keys, OAuth secrets, banking certificates, webhook secrets, and encryption keys.

11. Validate service-to-service trust.
Review how background jobs, webhooks, and banking integrations authenticate and whether they use narrow, scoped credentials.

12. Reassess network and infrastructure trust.
Check whether internal services, admin tools, queues, storage, and databases are implicitly trusted instead of explicitly authenticated and authorized.

13. Add security test coverage.
Create tests for unauthorized access, cross-user access attempts, stale-session flows, and high-risk actions requiring re-verification.

14. Define a zero trust target state.
Write a short internal standard for what this app should require for identity, device/session trust, authorization, auditability, and external integrations.
