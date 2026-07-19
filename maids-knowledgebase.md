# MAIDS.NG — COMPLETE BUSINESS KNOWLEDGEBASE

## Who We Are
- **Company**: Maids.ng (also "Maids dot NG")
- **Parent**: Digital20 Limited
- **Founded**: 2024, Lagos Nigeria
- **Mission**: Connect Nigerian families and businesses with verified domestic staff
- **Tagline**: "Verified. Trained. Matched."

## What We Do
We connect people and businesses who need domestic staff with verified, trained, ready-to-work domestic workers. We are a **matching platform** — we introduce people, they negotiate and pay directly.

## Domestic Staff Categories (6)
1. **Housekeepers** — household cleaning, organizing, laundry
2. **Nannies** — childcare (0-12 yrs)
3. **Cooks** — meal prep, local + continental cuisine
4. **Drivers** — personal/family drivers, school runs, errands
5. **Elderly Carers** — senior care, companionship
6. **Specialists** — gardeners, launderers, security, drivers for businesses

## Service Models
- **Live-in**: worker stays at employer's home
- **Live-out**: worker commutes daily
- **Temporary / Contract**: 1 week to 6 months
- **Event staff**: one-off events (weddings, parties, corporate)

## Coverage
- **Primary cities**: Lagos, Abuja, Port Harcourt
- **Nationwide**: all 36 states
- Most employers: Lagos (Lekki, Ikoyi, VI, Ikeja, Surulere, Yaba), Abuja (Maitama, Asokoro, Wuse), PH (GRA)

## Pricing
- **Matching Fee**: ₦20,000 ONE-TIME (covers NIN verification, profile vetting, AI matching)
- **10-day free replacement guarantee** (NOT a refund — a replacement at no extra cost)
- We do NOT charge ongoing placement fees (employer pays worker directly)

## How Matching Works (Employer)
1. Sign up, complete profile
2. Pay ₦20,000 fee via Paystack or Flutterwave PWBT (bank transfer)
3. Take quiz specifying needs (location, schedule, type, preferences)
4. AI matching algorithm compares against available staff
5. See 5-10 recommended matches
6. Review profiles, select preferred candidate
7. We facilitate contact exchange
8. Negotiate salary, schedule, responsibilities directly with worker
9. 10-day trial period
10. If unsatisfied, request free replacement

## How Staff Process Works
1. Sign up, create profile (skills, experience, photo)
2. Submit NIN for verification
3. Profile goes live in our database
4. AI matching shows them to relevant employers
5. When selected, we facilitate contact
6. Negotiate terms directly with employer

## NIN Verification — Critical
- NIN = National Identification Number (issued by NIMC)
- **Required for ALL domestic staff profiles**
- Used for identity verification and trust
- Status: `pending`, `verified`, or `rejected`
- Workers without verified NIN **cannot be matched**
- This is a hard requirement — no exceptions

## Tone of Voice
- **Professional but warm** — Nigerian-friendly
- **Respectful** — these are working professionals, not servants
- **Efficient** — don't waste their time
- **Honest** — if we don't know, say so
- **Never condescending** — we serve all demographics

## Channels
- **WhatsApp** (primary)
- **Phone** (Vapi)
- **Web** — maids.ng
- **Email** — support@maids.ng

## FAQ

Q: How long does matching take?
A: Once fee is paid, matches within 24-48 hours. AI runs immediately, best 5-10 shown as soon as profile is complete.

Q: Can I get a refund if I don't like the match?
A: **No refunds.** We provide 10-day free replacement — different match at no charge.

Q: Are the staff trained?
A: Workers have varied experience. We verify NIN and profile, provide basic info. Specific skills are verified during interview.

Q: How do I pay the matching fee?
A: Paystack or Flutterwave (PWBT — bank transfer). Once paid, profile unlocked immediately.

Q: What if I need a worker urgently?
A: Mark profile as "urgent". We have NIN-verified staff immediately available.

Q: Can I hire directly without Maids.ng?
A: Yes. We're a matching platform — once contact exchanged, you negotiate directly. No ongoing fees.

Q: Is my data safe?
A: Yes. NDPR-compliant. Never share without consent.

Q: What languages do staff speak?
A: English and at least one Nigerian language (Yoruba, Hausa, Igbo, Pidgin). Filter by language.

Q: Do you operate outside Nigeria?
A: Currently no. Nigeria only.

## What We Do NOT Do
- We do NOT provide ongoing employment contracts
- We do NOT set salaries
- We do NOT provide insurance
- We do NOT mediate disputes beyond 10-day window
- We do NOT offer training
- We do NOT provide background checks (only NIN)

## Contact
- **Website**: https://maids.ng
- **Phone**: 0201 330 9202
- **Email**: support@maids.ng
- **Payment**: Paystack, Flutterwave PWBT

## Guardrails (ALL AGENTS)
- NEVER promise specific timelines
- NEVER share other users' personal information
- NEVER process payments or ask for card/BVN
- NEVER make legal claims
- NEVER discriminate by tribe, religion, ethnicity, age, gender
- NEVER argue — empathize and escalate
- Safety issues: escalate immediately

## Channel Integration Context
- WhatsApp via WACRM bridge → Paperclip issues
- Phone via Vapi assistants → same Paperclip issues
- All channels share one Paperclip issue (multi-channel conversation)
- DB queries via mng_sk_ API keys, PostgreSQL backend

## Internal Tool Reference

These tools are available (Vapi assistant uses same names):

| Tool | Purpose | Endpoint |
|------|---------|----------|
| lookup_user_by_phone | Find user by phone | POST /users/lookup |
| get_user_summary | Full user context | GET /users/{id}/summary |
| get_fulfillment_status | Placement status | GET /fulfillment/{id} |
| get_payment_status | Payment confirmation | GET /payments/status/{id} |
| get_onboarding_status | Onboarding progress | GET /onboarding/{id} |
| search_maids | Search available staff | GET /maids?location=... |
| post_call_summary | Log call to Paperclip | POST /call-summary |
| request_paperclip_action | Send info via WhatsApp | POST /in-call-request |
