<?php
require_once __DIR__ . '/../includes/db.php';

function escapeTgHtml($text) {
    return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
}

function buildQuizParts($title, $questions, $promoSettings, $includeExplanation = false) {
    $totalQ = count($questions);
    
    // Build Question Blocks
    $qBlocks = [];
    $answerKeyItems = [];
    
    foreach ($questions as $idx => $q) {
        $num = $idx + 1;
        $qText = trim($q['question_text']);
        
        $lines = [];
        $lines[] = "📝 <b>Question {$num}/{$totalQ}</b>\n";
        $lines[] = escapeTgHtml($qText) . "\n";
        
        foreach (['A' => 'option_a', 'B' => 'option_b', 'C' => 'option_c', 'D' => 'option_d'] as $letter => $field) {
            $val = trim($q[$field] ?? '');
            if ($val !== '') {
                // If it doesn't already start with letter
                if (!preg_match('/^[A-D][\.\)\s-]/iu', $val)) {
                    $optText = "<b>{$letter}.</b> " . escapeTgHtml($val);
                } else {
                    $optText = escapeTgHtml($val);
                }
                $lines[] = $optText;
            }
        }
        
        if ($includeExplanation && !empty($q['explanation'])) {
            $lines[] = "\n💡 <i>Explanation: " . escapeTgHtml(trim($q['explanation'])) . "</i>";
        }
        
        $qBlocks[] = implode("\n", $lines);
        
        $correct = strtoupper(trim($q['correct_answer'] ?? $q['correct_option'] ?? ''));
        $ansLine = "{$num}. <b>{$correct}</b>";
        $answerKeyItems[] = $ansLine;
    }
    
    // Build Answer Key Block
    $answerKeyLines = [];
    $answerKeyLines[] = "📌 <b>ANSWER KEY</b>\n";
    $answerKeyLines[] = implode("\n", $answerKeyItems);
    $answerKeyBlock = implode("\n", $answerKeyLines);
    
    // Build Promo Block
    $promoLines = [];
    $groupLink = trim($promoSettings['group_link'] ?? '');
    $groupName = trim($promoSettings['group_name'] ?? 'Our Telegram Group');
    $adminUsername = trim($promoSettings['admin_username'] ?? '');
    $adminId = trim($promoSettings['admin_id'] ?? '');
    $footerMsg = trim($promoSettings['footer_message'] ?? 'Stay connected for more quizzes, mock tests and exam preparation.');
    
    $promoLines[] = "📢 <b>JOIN OUR TELEGRAM GROUP</b>\n";
    if ($groupLink) {
        $promoLines[] = "<a href=\"" . escapeTgHtml($groupLink) . "\">" . escapeTgHtml($groupName ?: $groupLink) . "</a>\n" . escapeTgHtml($groupLink);
    } else {
        $promoLines[] = escapeTgHtml($groupName);
    }
    
    $promoLines[] = "\n👤 <b>Contact / Telegram Admin:</b>";
    if ($adminUsername) {
        $cleanUser = ltrim($adminUsername, '@');
        $promoLines[] = "<a href=\"https://t.me/" . escapeTgHtml($cleanUser) . "\">@" . escapeTgHtml($cleanUser) . "</a>";
    }
    if ($adminId) {
        $promoLines[] = "User ID: <code>" . escapeTgHtml($adminId) . "</code>";
    }
    
    if ($footerMsg) {
        $promoLines[] = "\n" . escapeTgHtml($footerMsg);
    }
    
    $promoBlock = implode("\n", $promoLines);
    
    // Now segment into parts (limit: 3800 chars per part)
    $MAX_LEN = 3800;
    $separator = "\n\n━━━━━━━━━━━━━━━━\n\n";
    
    // Header template
    $makeHeader = function($partNum, $totalParts) use ($title, $totalQ) {
        $partStr = $totalParts > 1 ? " (Part {$partNum}/{$totalParts})" : "";
        return "🎯 <b>" . escapeTgHtml(mb_strtoupper($title, 'UTF-8')) . "</b>{$partStr}\n\n"
             . "📚 <b>Total Questions:</b> {$totalQ}\n\n"
             . "━━━━━━━━━━━━━━━━\n\n";
    };
    
    // First package question blocks
    $qChunks = [];
    $curQChunk = [];
    $headerLenEstimate = 200;
    $curLen = $headerLenEstimate;
    
    foreach ($qBlocks as $block) {
        $blockLen = mb_strlen($block . $separator, 'UTF-8');
        if ($curLen + $blockLen > $MAX_LEN && !empty($curQChunk)) {
            $qChunks[] = $curQChunk;
            $curQChunk = [$block];
            $curLen = $headerLenEstimate + $blockLen;
        } else {
            $curQChunk[] = $block;
            $curLen += $blockLen;
        }
    }
    if (!empty($curQChunk)) {
        $qChunks[] = $curQChunk;
    }
    
    // Now check if Answer Key + Promo block fits in the last chunk
    $tailBlock = $answerKeyBlock . $separator . $promoBlock;
    $tailLen = mb_strlen($separator . $tailBlock, 'UTF-8');
    
    $lastChunkIdx = count($qChunks) - 1;
    if ($lastChunkIdx >= 0) {
        $lastChunkQuestionsLen = $headerLenEstimate;
        foreach ($qChunks[$lastChunkIdx] as $b) {
            $lastChunkQuestionsLen += mb_strlen($b . $separator, 'UTF-8');
        }
        
        if ($lastChunkQuestionsLen + $tailLen <= $MAX_LEN) {
            $hasSeparateTailChunk = false;
        } else {
            $hasSeparateTailChunk = true;
        }
    } else {
        $hasSeparateTailChunk = true;
    }
    
    $totalParts = count($qChunks) + ($hasSeparateTailChunk ? 1 : 0);
    if ($totalParts === 0) $totalParts = 1;
    
    // Now build actual messages with accurate headers
    $messages = [];
    foreach ($qChunks as $pIdx => $blocks) {
        $partNum = $pIdx + 1;
        $isLastQChunk = ($pIdx === count($qChunks) - 1);
        
        $msg = $makeHeader($partNum, $totalParts);
        $msg .= implode($separator, $blocks);
        
        if ($isLastQChunk && !$hasSeparateTailChunk) {
            $msg .= $separator . $tailBlock;
        }
        $messages[] = $msg;
    }
    
    if ($hasSeparateTailChunk) {
        $partNum = count($messages) + 1;
        $msg = $makeHeader($partNum, $totalParts);
        $msg .= $tailBlock;
        $messages[] = $msg;
    }
    
    return [
        'total_parts' => count($messages),
        'total_questions' => $totalQ,
        'messages' => $messages
    ];
}

// Test with Quiz Set 2
$qs = $pdo->query("SELECT * FROM tg_quiz_questions WHERE quiz_set_id = 2 ORDER BY question_number ASC")->fetchAll(PDO::FETCH_ASSOC);
$setRow = $pdo->query("SELECT * FROM tg_quiz_sets WHERE id = 2")->fetch(PDO::FETCH_ASSOC);

$promo = [
    'group_name' => 'Bankers Lifes❤️',
    'group_link' => 'https://t.me/bankerslifes',
    'admin_username' => '@Abhijeet_Mishra',
    'admin_id' => '9272349548',
    'footer_message' => 'Stay connected for more quizzes, mock tests and exam preparation.'
];

$res = buildQuizParts($setRow['title'], $qs, $promo, false);
echo "SUCCESS!\n";
echo "Total Parts: " . $res['total_parts'] . "\n";
echo "Total Questions: " . $res['total_questions'] . "\n";
foreach ($res['messages'] as $i => $m) {
    echo "\n==================== PART " . ($i + 1) . " of " . $res['total_parts'] . " (Length: " . mb_strlen($m, 'UTF-8') . " chars) ====================\n";
    echo $m . "\n";
}
