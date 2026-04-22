Can you create this using HTML and Tailwind CSS. 
Use Gold, Lemon Green Color scheme.

Maids.ng — AI-First, Card-Based PRP (Vibe-Coding Ready)
Below is a single, actionable Product Requirement Plan (PRP) optimized for vibe coding and fast delivery. It turns Maids.ng into an AI-first, card-driven experience (not chat-only), with built-in virality, robust agency features, and UX designed for low-literacy/voice-first users. 

1 — Concept: AI-first + Card UX
Primary landing choices (big, tappable cards):

I need a Maid (Employer/Household flow)

I need a Maid Job (Helper flow)
Each step after that is presented as a card (one question / small choice set). The AI (Trae/LLM) dynamically generates follow-up cards: 3 suggested answers + an “Other / Type answer” input. The user taps a suggestion or types (or records voice). Cards advance until the goal: match / application / booking is completed.

Why cards: faster choices, less typing, easier for low-literacy, works well for sharing on WhatsApp/social.

2 — High Level Flows
A. Household Flow (I need a Househelp)
Landing card → tap I need a Househelp

Card: “What kind of help?” → options: Fulltime Maid, Cleaner, Cook, Nanny, Other (type)

Card: “Live-in or Live-out?” → options: Live-in, Live-out, Either

Card: “Location (City / Area)?” → options: recent cities or Use my phone location or Type

Card: “When do you need them?” → Immediately, Within a week, From date (input)

Card: “Budget / Monthly Rate?” → preset ranges + custom input

Card: “Special needs / preferences?” → AI proposes 3 common filters (language, experience, religion) + Other

Summary card: shows AI’s 3 best matches (cards with large photo, 3 bullets, verification badge, price, CTA: Hire Maid / Share)

Actions:

Hire Help → triggers booking card flow (select date, payment method) (offer free replacement within 10days)

Message → opens simplified chat for negotiation (card-based messages)

Share → shareable WhatsApp card with helper mini-profile and deep link

Post-hire: AI nudges the household to leave a short review (AI suggests text they can edit)

B. Helper Flow (I need a Maid Job)
Landing card → tap I need a Maid Job

Card: “What work are you looking for?” → options reflect job types

Card: “Are you available now?” → Yes / From date

Card: “Voice intro” → record a 20s voice clip in local language (optional but recommended)

Card: Collect required ID via camera/photo upload (AI verifies image quality; prompts re-take if poor)

Card: “Preferred location / commute” → quick picks

Card: “Skills” → AI suggests skills from voice input + 3 suggestions; helper taps checkboxes

Summary card: helper profile preview + Publish profile button

Once published: AI suggests immediate matching jobs (3 cards) and gives referral invite to other helpers

Helpers earn badges (Bronze → Silver → Gold) based on verification, reviews, invites

3 — Card Component Spec (UI / Interaction)
Card anatomy (mobile-first)
Header (icon + short question)

3 suggested response buttons (large, tappable)

Other / Type input (text field or voice record button)

Help icon (plays audio hint in local language)

Progress indicator (e.g., Step 3 of 8)

Back/Skip small buttons

Micro-interactions
Tapping suggestion animates card out and new card slides in.

Long-press a helper profile card to see a full-screen modal with gallery, voice intro, documents, and reviews.

Haptic feedback on selection (mobile).

Offline: queue actions; show “pending sync” badge until online.

Accessibility & Local-Language
Each card has an audio play button for the question in Yoruba/Hausa/Igbo/Pidgin (TTS).

Larger fonts, high-contrast CTAs, and clear icons.

Voice input fallback: tap mic → record → transcribed by AI → confirmation card.

4 — AI Responsibilities (where LLM / Trae helps)
On every card step:
Generate the 3 best suggestions (context-aware).

Convert voice → text (STT), validate, and pick top suggestion.

Validate document photos (quality checks, blur detection).

Suggest short review copy and message templates.

Suggest salary/price ranges based on city & role (optional ML model or simple rules).

Auto-fill repetitive household preferences for faster matching.

Matching
Multi-factor scoring: location proximity, skills match, verification status, helper level badge, availability, price.

Use embeddings + vector DB for semantic matching (optional).

AI returns top-3 ranked helpers with match-score explanations (“Speaks Yoruba, 4 years experience, Verified ID”).

Dialog fallbacks
If AI cannot decide: show “Human assist” escalate button → routes to an admin/agency rep.

If photos or documents fail: show precise instruction card (e.g., “Move phone closer, ensure name is visible”).

