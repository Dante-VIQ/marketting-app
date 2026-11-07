<?php

namespace App\Services\ContentPlan;

/**
 * Turns the signals the system already collects into "what could we post about", with the reason.
 * Pure: no DB, no network. Every suggestion says where it came from so you can judge it.
 */
class ContentSuggester
{
    /**
     * @param array $topicGaps     CompetitorParser::topicGaps rows: term, competitors, mentions
     * @param array $conversations listening conversations: title, url, comments, score
     * @param array $striking      keywords ranking 4-20: keyword, position, volume
     * @param array $personas      persona summaries: name, pains
     * @param array $existing      lower-cased titles/topics already planned, to avoid repeats
     * @param array $questions     real questions people asked in comments on our posts (Meta)
     */
    public static function suggest(array $topicGaps, array $conversations, array $striking, array $personas, array $existing = [], int $limit = 12, array $questions = []): array
    {
        $seen = array_flip(array_map('mb_strtolower', $existing));
        $out = [];
        $add = function (array $s) use (&$out, &$seen) {
            $key = mb_strtolower($s['topic']);
            if ($key === '' || isset($seen[$key])) {
                return;
            }
            $seen[$key] = true;
            $out[] = $s;
        };

        foreach ($questions as $q) {
            $q = trim((string) $q);
            if ($q === '') {
                continue;
            }
            $short = mb_strimwidth($q, 0, 90, '…');
            $add(['title' => 'Answer: ' . $short, 'topic' => $q, 'source' => 'audience_comment',
                'source_detail' => 'A real question from the comments on our posts.']);
        }
        foreach ($striking as $k) {
            $add(['title' => "Improve or expand content for \"{$k['keyword']}\"", 'topic' => (string) $k['keyword'], 'source' => 'keyword',
                'source_detail' => "Ranks #{$k['position']}" . (isset($k['volume']) ? ", about {$k['volume']} searches a month" : '') . ': close enough to reach page one.']);
        }
        foreach ($topicGaps as $g) {
            $add(['title' => "Cover \"{$g['term']}\"", 'topic' => (string) $g['term'], 'source' => 'competitor_gap',
                'source_detail' => "{$g['competitors']} competitor" . ($g['competitors'] === 1 ? '' : 's') . ' write about this and you do not.']);
        }
        foreach ($conversations as $c) {
            if (empty($c['title'])) {
                continue;
            }
            $add(['title' => 'Join the conversation: ' . $c['title'], 'topic' => (string) $c['title'], 'source' => 'listening',
                'source_detail' => 'Active public discussion' . (!empty($c['url']) ? ': ' . $c['url'] : '') . '.']);
        }
        foreach ($personas as $p) {
            foreach (array_slice($p['pains'] ?? [], 0, 1) as $pain) {
                $add(['title' => "Address: {$pain}", 'topic' => (string) $pain, 'source' => 'persona',
                    'source_detail' => "Top pain for the persona \"{$p['name']}\"."]);
            }
        }

        return array_slice($out, 0, $limit);
    }
}
