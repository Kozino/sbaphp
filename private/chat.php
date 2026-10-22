<?php
declare(strict_types=1);

/** Chatbot: constitution + live site facts → Groq (OpenAI-compatible) API. */

function chat_site_context(): string
{
    $execs = all('SELECT name, position FROM executives WHERE tenure_id IS NULL ORDER BY sort_order, id');
    $news = all('SELECT title, date, body FROM news ORDER BY date DESC, id DESC LIMIT 8');
    $gallery = all('SELECT title, event_year FROM gallery ORDER BY event_year DESC, id DESC LIMIT 15');
    $members = (int)val("SELECT COUNT(*) FROM members WHERE status = 'active'");
    $s = settings();

    $execLines = $execs ? implode("\n", array_map(fn($e) => "- {$e['position']}: {$e['name']}", $execs)) : 'No executives are currently listed on the site.';
    $newsLines = $news ? implode("\n", array_map(
        fn($n) => '- [' . substr((string)$n['date'], 0, 10) . '] ' . $n['title'] . ' — ' . snippet((string)$n['body'], 200),
        $news
    )) : 'No news items are currently posted.';
    $galLines = $gallery ? implode("\n", array_map(fn($g) => "- {$g['title']} ({$g['event_year']})", $gallery)) : 'No gallery events are currently listed.';

    return "CURRENT EXECUTIVES:\n{$execLines}\n\n"
        . "CONTACT INFO:\nEmail: {$s['contact_email']}\nPhone: {$s['contact_phone']}\nAddress: {$s['contact_address']}\n\n"
        . "MEMBERSHIP: The association currently has {$members} active members.\n\n"
        . "RECENT NEWS (most recent first):\n{$newsLines}\n\n"
        . "GALLERY / PAST EVENTS:\n{$galLines}";
}

function chat_answer(string $question): string
{
    $key = cfg()['groq_api_key'];
    if ($key === '') {
        return 'The assistant is not switched on yet. Please contact the executives directly.';
    }
    $constitution = (string)file_get_contents(__DIR__ . '/constitution.txt');
    $system = "You are the assistant for the Savio Bosco Alphas 2012 (SBA 2012) website. Answer questions using the constitution text and the current site information provided below. If the answer isn't in either, say so plainly and suggest the person contact the association's executives — do not guess or make anything up. Keep answers concise and friendly, in plain English. Do not mention that you were given documents or data.\n\nCONSTITUTION:\n{$constitution}\n\n" . chat_site_context();

    $payload = json_encode([
        'model' => 'openai/gpt-oss-20b',
        'max_tokens' => 600,
        'messages' => [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $question],
        ],
    ], JSON_UNESCAPED_UNICODE);

    $ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $key],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
    ]);
    $body = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $data = is_string($body) ? json_decode($body, true) : null;
    if ($code !== 200 || !is_array($data)) {
        throw new RuntimeException('upstream');
    }
    $answer = trim((string)($data['choices'][0]['message']['content'] ?? ''));
    return $answer !== '' ? $answer : "Sorry, I couldn't find an answer to that.";
}
