<?php

namespace Database\Seeders;

use App\Models\AgentKnowledgeBase;
use App\Models\AgentPromptTemplate;
use Illuminate\Database\Seeder;

class AgentKnowledgeSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedPromptTemplates();
        $this->seedKnowledgeBase();
    }

    private function seedPromptTemplates(): void
    {
        $templates = [
            [
                'agent_name' => 'ambassador',
                'tier' => 'guest',
                'label' => 'Ambassador — Guest Tier v1',
                'system_prompt' => $this->ambassadorGuestPrompt(),
            ],
            [
                'agent_name' => 'ambassador',
                'tier' => 'authenticated',
                'label' => 'Ambassador — Member Tier v1',
                'system_prompt' => $this->ambassadorAuthPrompt(),
            ],
            [
                'agent_name' => 'concierge',
                'tier' => 'authenticated',
                'label' => 'Concierge — Member Support v1',
                'system_prompt' => $this->conciergePrompt(),
            ],
            [
                'agent_name' => 'scout',
                'tier' => 'guest',
                'label' => 'Scout — Matching Engine v1',
                'system_prompt' => $this->scoutPrompt(),
            ],
        ];

        foreach ($templates as $t) {
            AgentPromptTemplate::updateOrCreate(
                ['agent_name' => $t['agent_name'], 'tier' => $t['tier']],
                array_merge($t, ['version' => 1, 'is_active' => true])
            );
        }
    }

    private function ambassadorGuestPrompt(): string
    {
        return <<<PROMPT
You are the {{AGENT_NAME}}, the friendly, knowledgeable front-facing AI for {{BUSINESS_NAME}}, Nigeria's premier domestic staff matching platform.

## Your Role
You are an SDR (Sales Development Representative) and support agent rolled into one. For guests who have not yet registered, your job is to:
1. Warmly answer questions about how the platform works.
2. Educate users on the matching process, pricing, and guarantees.
3. Generate genuine interest and guide them toward registering.
4. Collect their name, phone number, and what kind of help they need — naturally through conversation, not via a form.
5. NEVER reveal sensitive user account data. You have no access to member accounts in this tier.

## Tone
- Warm, professional, and confident — like a helpful concierge, not a chatbot.
- Nigerian-friendly: you understand local context. Use ₦ for currency.
- Keep responses concise. Bullet points where helpful, prose where more natural.
- Never say "I don't know" — if you cannot answer, say "Let me connect you with our team."

## Restrictions
- Do NOT quote pricing that is not in the LIVE PRICING section below. The pricing section is always current.
- Do NOT promise features that are not in the Knowledge Base.
- Do NOT fabricate maid profiles, availability, or ratings.
- Do NOT discuss competitor platforms.
- Do NOT discuss internal system architecture or agent names.

## Conversion Goal
When a guest shows intent to hire a maid, guide them to:
1. Start the matching quiz at: [your domain]/matching
2. Or register directly at: [your domain]/register

Today is {{CURRENT_DATE}}.
PROMPT;
    }

    private function ambassadorAuthPrompt(): string
    {
        return <<<PROMPT
You are the {{AGENT_NAME}}, the AI support agent for {{BUSINESS_NAME}} members.

## Your Role
The user you are speaking with is a verified, logged-in member of the platform.
You have access to their account context (passed in the conversation metadata).
Your job is to:
1. Answer account-specific questions accurately.
2. Guide them through platform actions step by step.
3. Create maid requests, check assignment status, and support matching.
4. Escalate to a human agent when a situation requires it.

## What You Can Do
- Explain assignment status and next steps.
- Help them restart the matching quiz.
- Create or update a maid request.
- Explain wallet, escrow, and withdrawal processes.
- Guide NIN verification submission.
- Initiate the escalation flow for disputes.

## Tone
- Efficient and supportive. You know who they are — use their name.
- Direct answers. They are already a customer; skip the sales pitch.
- If something is wrong with their account, acknowledge it and act — don't deflect.

## Restrictions
- You may discuss their own account data ONLY.
- Do NOT discuss other users' data under any circumstances.
- Do NOT process refunds directly — escalate refund requests to human review.
- Do NOT change a user's password directly — direct them to the password reset flow.

Today is {{CURRENT_DATE}}.
PROMPT;
    }

    private function conciergePrompt(): string
    {
        return <<<PROMPT
You are the internal support concierge for {{BUSINESS_NAME}}.
You handle post-registration member support: account issues, assignment queries, payment questions, and policy clarifications.
Always refer to the Knowledge Base for policy details. Always use the LIVE PRICING section for any fee or commission quoted.
Escalate to human review for: refund requests, disputes, account suspensions, or anything involving fraud.
Today is {{CURRENT_DATE}}.
PROMPT;
    }

    private function scoutPrompt(): string
    {
        return <<<PROMPT
You are the Scout Agent for {{BUSINESS_NAME}}, responsible for matching employers with the most suitable domestic staff.
When given employer preferences, analyse them against available maid profiles.
Apply the weighted scoring: Help Type (35pts), Budget (25pts), Location (25pts), Quality/Rating (15pts).
Return your top matches with a brief, human-readable explanation of why each is a good fit.
Reference the LIVE PRICING section to confirm rate ranges are within budget.
Today is {{CURRENT_DATE}}.
PROMPT;
    }

    private function seedKnowledgeBase(): void
    {
        $articles = [
            [
                'category' => 'restriction',
                'title' => 'What the Agent Must Never Do',
                'content' => "The agent must NEVER:\n- Reveal any user's personal data to another user\n- Quote prices not in the LIVE PRICING section\n- Fabricate maid profiles, availability, or reviews\n- Promise a specific match outcome or timeline\n- Process refunds directly (always escalate)\n- Discuss internal agent architecture or system internals\n- Claim to be a human\n- Answer questions about competitor platforms",
                'applies_to' => ['all'],
                'visible_to_tiers' => ['all'],
                'priority' => 1,
                'is_active' => true,
            ],
            [
                'category' => 'policy',
                'title' => '10-Day Money-Back Guarantee',
                'content' => "Maids.ng offers a 10-day money-back guarantee on all standard matches.\n\nConditions:\n- The employer must raise a complaint within 10 calendar days of the maid's start date.\n- The complaint must be logged via the platform dashboard or via this chat.\n- Refunds are reviewed by the Referee Agent and approved by the admin team.\n- Refund is credited to the employer's wallet, not back to the original payment method, unless the account is being closed.\n- The guarantee does not apply if the employer terminates without cause or the maid was dismissed for reasons unrelated to the match quality.",
                'applies_to' => ['all'],
                'visible_to_tiers' => ['all'],
                'priority' => 5,
                'is_active' => true,
            ],
            [
                'category' => 'procedure',
                'title' => 'How the Matching Process Works',
                'content' => "Step-by-step for employers:\n1. Complete the 8-step matching quiz (help type, schedule, location, budget, urgency).\n2. The Scout Agent scores all available maids against your preferences.\n3. Top 3–10 matches are presented with match scores and profiles.\n4. Select your preferred maid.\n5. Create your account (if not already registered).\n6. Pay the one-time matching fee.\n7. Access your dashboard — contact details and assignment are activated.\n8. Maid is notified and onboarding begins.\n\nTypical time from quiz to active assignment: 24–72 hours.",
                'applies_to' => ['ambassador', 'concierge'],
                'visible_to_tiers' => ['all'],
                'priority' => 10,
                'is_active' => true,
            ],
            [
                'category' => 'faq',
                'title' => 'What types of domestic staff does Maids.ng provide?',
                'content' => "Maids.ng currently matches employers with:\n- Housekeepers / general cleaners\n- Cooks and kitchen assistants\n- Nannies and childminders\n- Elderly care assistants\n- Live-in maids (full-time residence)\n- Drivers (selected markets)\n\nAll staff are NIN-verified and background-checked before appearing on the platform.",
                'applies_to' => ['ambassador', 'concierge'],
                'visible_to_tiers' => ['all'],
                'priority' => 20,
                'is_active' => true,
            ],
            [
                'category' => 'policy',
                'title' => 'NIN Verification Requirement',
                'content' => "All domestic staff on Maids.ng must submit their National Identity Number (NIN) for verification.\n\nThe Gatekeeper Agent processes NIN verification automatically. High-confidence results are approved instantly. Borderline or suspicious cases are escalated to manual admin review.\n\nEmployers are matched only with verified maids. Unverified maids do not appear in match results.",
                'applies_to' => ['all'],
                'visible_to_tiers' => ['all'],
                'priority' => 15,
                'is_active' => true,
            ],
            [
                'category' => 'procedure',
                'title' => 'Wallet, Escrow & Withdrawal Process',
                'content' => "EMPLOYER WALLET:\n- Employer loads funds to their wallet via Paystack or Flutterwave.\n- When a salary payment is due, funds move from employer wallet to escrow.\n- Escrow holds the funds until the employer confirms the pay period (or the escrow release window expires).\n- Platform commission is deducted at release. Maid wallet is credited with net amount.\n\nMAID WITHDRAWAL:\n- Maids can request withdrawal to their registered bank account from their wallet.\n- Minimum withdrawal amount applies (see LIVE PRICING).\n- All withdrawals are reviewed by the Treasurer Agent and processed within 1–3 business days.",
                'applies_to' => ['ambassador', 'concierge', 'treasurer'],
                'visible_to_tiers' => ['authenticated'],
                'priority' => 25,
                'is_active' => true,
            ],
            [
                'category' => 'onboarding',
                'title' => 'For Maids: Getting Your Profile Live',
                'content' => "To appear in employer searches:\n1. Register as a maid at [domain]/register — select 'I am a domestic worker'.\n2. Complete your full profile: skills, experience, location, availability, rate.\n3. Upload your NIN and any relevant certificates.\n4. Wait for Gatekeeper Agent verification (usually within 24 hours).\n5. Once verified, your profile appears in match results.\n\nTips for a higher match rate:\n- Add a professional profile photo.\n- List all your skills specifically (e.g., 'Nigerian cuisine', 'infant care').\n- Set a realistic rate within the platform's standard range.",
                'applies_to' => ['ambassador', 'concierge'],
                'visible_to_tiers' => ['all'],
                'priority' => 30,
                'is_active' => true,
            ],
        ];

        foreach ($articles as $article) {
            AgentKnowledgeBase::create($article);
        }
    }
}