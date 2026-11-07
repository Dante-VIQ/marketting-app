<?php

namespace App\Services\Meta;

/**
 * What the free Meta APIs can give us, and which token permission unlocks each thing.
 * Pure: feed it the scopes the token really has (from debug_token) and it says what is available.
 */
class MetaCapabilities
{
    /** key => [group, label, required scopes, what the app does with it, built?] */
    public const MAP = [
        'pages_list'      => ['Facebook Page', 'List the Pages you manage', ['pages_show_list'], 'Finds your Page and Instagram account', true],
        'page_posts'      => ['Facebook Page', 'Read Page posts, reactions, comments and shares', ['pages_read_engagement'], 'Imports your recent posts so performance has history on day one', true],
        'page_insights'   => ['Facebook Page', 'Page and post views, clicks and followers', ['read_insights'], 'Fills in results for the Content Plan automatically', true],
        'page_publish'    => ['Facebook Page', 'Publish posts to your Page', ['pages_manage_posts'], 'Approved posts go out at the scheduled time', true],
        'page_comments'   => ['Facebook Page', 'Read comments and ratings left by people', ['pages_read_user_content', 'pages_read_engagement'], 'Saves what people say under your posts as customer voice for personas', true],
        'ig_media'        => ['Instagram', 'Read your Instagram profile and posts', ['instagram_basic'], 'Imports your recent Instagram posts', true],
        'ig_insights'     => ['Instagram', 'Instagram post and account insights, audience demographics', ['instagram_manage_insights'], 'Results, and later best times to post', true],
        'ig_publish'      => ['Instagram', 'Publish photos to Instagram', ['instagram_content_publish', 'instagram_basic', 'pages_read_engagement', 'pages_show_list'], 'Approved image posts go out at the scheduled time', true],
        'ig_comments'     => ['Instagram', 'Read and manage Instagram comments', ['instagram_manage_comments'], 'Saves what people say under your Instagram posts as customer voice', true],
        'ads_read'        => ['Ads', 'Read campaigns, ad sets, ads and performance with breakdowns', ['ads_read'], 'Pulls spend, clicks and reach by age, gender, country and placement', true],
        'ads_manage'      => ['Ads', 'Pause, resume and change budgets', ['ads_management'], 'Agent actions (human approved, budget change capped)', true],
        'audience_research' => ['Ads', 'Interest and audience-size research (free targeting search)', ['ads_read'], 'Sizes interests and audiences (free targeting search and reach estimate)', true],
        'lead_ads'        => ['Ads', 'Retrieve leads from Lead Ad forms', ['leads_retrieval', 'pages_manage_ads', 'pages_read_engagement', 'pages_show_list'], 'Pulls form leads into the Leads pipeline', true],
        'page_webhooks'   => ['Facebook Page', 'Subscribe to Page activity (webhooks)', ['pages_manage_metadata'], 'Future: instant lead and comment alerts instead of polling', false],
        'business'        => ['Business', 'See the Pages and ad accounts your Business owns', ['business_management'], 'Discovery of assets', false],
        'whatsapp_manage' => ['WhatsApp', 'Manage WhatsApp Business assets and templates', ['whatsapp_business_management'], 'Future: WhatsApp leads', false],
        'whatsapp_message' => ['WhatsApp', 'Send and receive WhatsApp messages', ['whatsapp_business_messaging'], 'Future: WhatsApp leads and follow-ups', false],
    ];

    /** The one token to generate: every permission the built features use, plus the base ones they depend on. */
    public static function recommendedScopes(): array
    {
        $all = [];
        foreach (self::MAP as [, , $req, , $built]) {
            if ($built) {
                $all = array_merge($all, $req);
            }
        }

        return array_values(array_unique(array_merge($all, ['business_management'])));
    }

    /** @param string[] $granted scopes the token holds */
    public static function evaluate(array $granted): array
    {
        $have = array_flip($granted);
        $out = [];
        foreach (self::MAP as $key => [$group, $label, $req, $uses, $built]) {
            $missing = array_values(array_filter($req, fn ($s) => !isset($have[$s])));
            $out[] = ['key' => $key, 'group' => $group, 'label' => $label, 'requires' => $req, 'missing' => $missing,
                'available' => $missing === [], 'used_for' => $uses, 'built' => $built];
        }

        return $out;
    }

    /** Scopes to add to the token to unlock everything the app already uses. */
    public static function missingForBuilt(array $granted): array
    {
        $missing = [];
        foreach (self::evaluate($granted) as $c) {
            if ($c['built']) {
                $missing = array_merge($missing, $c['missing']);
            }
        }

        return array_values(array_unique($missing));
    }

    public static function has(array $granted, string $capability): bool
    {
        foreach (self::evaluate($granted) as $c) {
            if ($c['key'] === $capability) {
                return $c['available'];
            }
        }

        return false;
    }
}
