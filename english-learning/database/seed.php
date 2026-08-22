<?php
require_once __DIR__ . '/../config/database.php';

// Add Categories
$pdo->exec("INSERT IGNORE INTO exam_categories (id, name, slug) VALUES 
(1, 'SSC CGL', 'ssc-cgl'),
(2, 'Bank PO', 'bank-po'),
(3, 'Railway', 'railway')");

// Idioms
$idioms = [
    ['Break the ice', 'break-the-ice', 'To initiate conversation in an awkward setting.', 'बातचीत की शुरुआत करना', 'Used when people meet for the first time.', 'The teacher told a joke to break the ice.', 'Ice (बर्फ) को तोड़कर रास्ता बनाना = बातचीत शुरू करना', 'Start, Initiate', 'Conclude', 'Easy'],
    ['Piece of cake', 'piece-of-cake', 'Something very easy to do.', 'बहुत आसान काम', '', 'The math test was a piece of cake.', 'Cake खाना बहुत आसान होता है', 'Breeze, Cinch', 'Hard, Difficult', 'Easy'],
    ['Bite the bullet', 'bite-the-bullet', 'To endure a painful or difficult situation bravely.', 'कठिन परिस्थिति का डटकर सामना करना', '', 'I have to bite the bullet and visit the dentist.', 'Bullet (गोली) चबाना = बहुत हिम्मत का काम', 'Endure, Brave', 'Avoid, Run away', 'Moderate'],
    ['Under the weather', 'under-the-weather', 'Feeling slightly ill.', 'बीमार महसूस करना', '', 'I am feeling a bit under the weather today.', 'खराब मौसम के नीचे होना = बीमार होना', 'Sick, Ill', 'Healthy, Well', 'Easy'],
    ['Spill the beans', 'spill-the-beans', 'To reveal a secret.', 'रहस्य उजागर करना', '', 'Come on, spill the beans! What is the surprise?', 'बीन्स को फैला देना = सब कुछ बता देना', 'Reveal, Disclose', 'Hide, Conceal', 'Moderate'],
    ['Once in a blue moon', 'once-in-a-blue-moon', 'Happening very rarely.', 'कभी-कभार', '', 'He cleans his room once in a blue moon.', 'Blue moon बहुत कम दिखता है', 'Rarely, Seldom', 'Frequently, Often', 'Easy'],
    ['Cost an arm and a leg', 'cost-an-arm-and-a-leg', 'To be very expensive.', 'बहुत महँगा होना', '', 'This sports car costs an arm and a leg.', 'हाथ और पैर के बदले = बहुत कीमती', 'Expensive, Costly', 'Cheap, Inexpensive', 'Moderate'],
    ['Hit the nail on the head', 'hit-the-nail-on-the-head', 'To say or do exactly the right thing.', 'बिल्कुल सही बात कहना', '', 'You hit the nail on the head with that answer.', 'कील के बिल्कुल सिर पर मारना = सटीक निशाना', 'Accurate, Precise', 'Inaccurate, Wrong', 'Hard'],
    ['Let the cat out of the bag', 'let-the-cat-out-of-the-bag', 'To reveal a secret by mistake.', 'गलती से रहस्य बता देना', '', 'She let the cat out of the bag about the surprise party.', 'बिल्ली को थैले से बाहर निकालना = छुपा हुआ बाहर आना', 'Disclose', 'Keep secret', 'Moderate'],
    ['Barking up the wrong tree', 'barking-up-the-wrong-tree', 'Pursuing a false lead or making a wrong assumption.', 'गलत दिशा में प्रयास करना', '', 'If you think I stole it, you are barking up the wrong tree.', 'गलत पेड़ पर भौंकना = गलत जगह खोजना', 'Mistaken', 'Correct', 'Moderate'],
    ['Burn the midnight oil', 'burn-the-midnight-oil', 'To study or work late into the night.', 'देर रात तक काम करना या पढ़ना', '', 'He had to burn the midnight oil for his exams.', 'आधी रात को तेल जलाना (दीया जलाना) = रात तक जागना', 'Work hard', 'Be lazy', 'Moderate'],
    ['Cry over spilled milk', 'cry-over-spilled-milk', 'To be upset over something that cannot be fixed.', 'बीती बात पर पछताना', '', 'It is no use crying over spilled milk; just buy a new phone.', 'गिरे हुए दूध पर रोना = व्यर्थ का पछतावा', 'Regret', 'Move on', 'Easy'],
    ['Cut corners', 'cut-corners', 'To do something poorly or cheaply to save time or money.', 'पैसे या समय बचाने के लिए काम ठीक से न करना', '', 'Don\'t cut corners when building a house.', 'कोने काटना = शॉर्टकट लेना', 'Skimp', 'Do properly', 'Moderate'],
    ['Hit the sack', 'hit-the-sack', 'To go to bed.', 'सोने जाना', '', 'I am exhausted, it is time to hit the sack.', 'बोरे (sack) पर गिरना = बिस्तर पर जाना', 'Go to sleep', 'Wake up', 'Easy'],
    ['On cloud nine', 'on-cloud-nine', 'To be extremely happy.', 'बहुत खुश होना', '', 'She was on cloud nine after getting the job.', 'नौवें बादल पर होना = खुशी के चरम पर', 'Ecstatic, Joyful', 'Depressed, Sad', 'Moderate'],
    ['See eye to eye', 'see-eye-to-eye', 'To agree with someone.', 'सहमत होना', '', 'My boss and I don\'t always see eye to eye.', 'आंख में आंख मिलाना = एक राय होना', 'Agree', 'Disagree', 'Moderate'],
    ['Taste of your own medicine', 'taste-of-your-own-medicine', 'Experiencing the same bad treatment that you have given to others.', 'जैसा किया वैसा भरना', '', 'The bully finally got a taste of his own medicine.', 'खुद की ही दवा का स्वाद = अपने कर्मों का फल', 'Retaliation', 'Forgiveness', 'Hard'],
    ['A blessing in disguise', 'a-blessing-in-disguise', 'A good thing that seemed bad at first.', 'छिपे हुए रूप में वरदान', '', 'Missing the train was a blessing in disguise because it derailed later.', 'भेष बदले हुए वरदान', 'Good luck', 'Bad luck', 'Moderate'],
    ['Add fuel to the fire', 'add-fuel-to-the-fire', 'To make a bad situation worse.', 'आग में घी डालना / स्थिति बिगाड़ना', '', 'Don\'t add fuel to the fire by arguing with him.', 'आग में ईंधन डालना', 'Worsen', 'Calm down', 'Easy'],
    ['Beat around the bush', 'beat-around-the-bush', 'To avoid talking about what is important.', 'घुमा-फिरा कर बात करना', '', 'Stop beating around the bush and tell me what happened.', 'झाड़ी के चारों ओर पीटना = मुख्य बिंदु पर न आना', 'Avoid, Evade', 'Be direct', 'Moderate']
];

$stmt = $pdo->prepare("INSERT IGNORE INTO idioms (idiom, slug, english_meaning, hindi_meaning, explanation, example_sentence, memory_trick, synonyms, antonyms, difficulty, status, category_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Published', 1)");
foreach ($idioms as $i) {
    $stmt->execute([$i[0], $i[1], $i[2], $i[3], $i[4], $i[5], $i[6], $i[7], $i[8], $i[9]]);
}

// Phrasal Verbs
$phrasals = [
    ['Give up', 'give-up', 'To stop trying.', 'हार मान लेना / छोड़ देना', '', 'Never give up on your dreams.', 'Give + Up = छोड़ देना', 'Surrender', 'Continue', 'Easy'],
    ['Look after', 'look-after', 'To take care of.', 'देखभाल करना', '', 'Can you look after my dog while I am away?', 'पीछे से देखना = ध्यान रखना', 'Take care', 'Neglect', 'Easy'],
    ['Bring up', 'bring-up', 'To raise a topic or a child.', 'पालन-पोषण करना / विषय उठाना', '', 'She brought up three children on her own.', 'ऊपर लाना = बड़ा करना', 'Raise, Mention', 'Ignore', 'Moderate'],
    ['Call off', 'call-off', 'To cancel an event or agreement.', 'रद्द करना', '', 'They had to call off the match due to rain.', 'बुलाकर हटा देना = रद्द करना', 'Cancel', 'Continue', 'Moderate'],
    ['Put off', 'put-off', 'To postpone.', 'स्थगित करना (टालना)', '', 'Don\'t put off until tomorrow what you can do today.', 'आगे रख देना = टालना', 'Postpone, Delay', 'Do immediately', 'Moderate'],
    ['Take off', 'take-off', 'To leave the ground (plane) or remove clothing.', 'उड़ान भरना / कपड़े उतारना', '', 'The plane will take off in ten minutes.', 'सतह से अलग होना', 'Depart', 'Land', 'Easy'],
    ['Look forward to', 'look-forward-to', 'To await something eagerly.', 'बेसब्री से इंतजार करना', '', 'I look forward to meeting you.', 'आगे की ओर देखना = उत्सुकता से प्रतीक्षा', 'Anticipate', 'Dread', 'Moderate'],
    ['Turn out', 'turn-out', 'To happen in a particular way.', 'साबित होना / नतीजा निकलना', '', 'The party turned out to be a great success.', 'बाहर निकल कर आना = परिणाम', 'Result', '', 'Moderate'],
    ['Break down', 'break-down', 'To stop working (machinery) or lose control of emotions.', 'खराब हो जाना / रो पड़ना', '', 'My car broke down on the highway.', 'टूट कर गिरना = काम करना बंद कर देना', 'Fail, Collapse', 'Work, Repair', 'Moderate'],
    ['Carry out', 'carry-out', 'To perform or complete a task.', 'अंजाम देना / पूरा करना', '', 'The soldiers carried out their orders.', 'बाहर ले जाना = काम खत्म करना', 'Execute', 'Ignore', 'Hard'],
    ['Come across', 'come-across', 'To find by chance.', 'संयोग से मिलना', '', 'I came across my old photos yesterday.', 'अचानक सामने आना', 'Find, Discover', 'Lose', 'Moderate'],
    ['Get along', 'get-along', 'To have a good relationship.', 'अच्छी निभना / तालमेल होना', '', 'I don\'t get along with my new boss.', 'साथ चलना = अच्छी बनना', 'Harmonize', 'Argue', 'Easy'],
    ['Give in', 'give-in', 'To surrender or yield.', 'झुक जाना / हार मान लेना', '', 'He finally gave in to their demands.', 'अंदर दे देना = झुक जाना', 'Submit', 'Resist', 'Moderate'],
    ['Hold on', 'hold-on', 'To wait for a short time.', 'थोड़ी देर रुकना', '', 'Hold on, I will be right back.', 'पकड़ कर रखना = इंतजार करना', 'Wait', 'Hurry', 'Easy'],
    ['Make up', 'make-up', 'To invent a story or reconcile.', 'कहानी गढ़ना / सुलह करना', '', 'He made up a lie to avoid punishment.', 'बनाना = गढ़ना', 'Invent, Reconcile', 'Tell truth, Argue', 'Moderate'],
    ['Pass away', 'pass-away', 'To die.', 'गुजर जाना / मृत्यु होना', '', 'Her grandfather passed away last night.', 'दूर गुजर जाना', 'Die', 'Live', 'Easy'],
    ['Run out of', 'run-out-of', 'To have none left.', 'खत्म हो जाना', '', 'We have run out of milk.', 'दौड़ कर खत्म होना', 'Exhaust', 'Replenish', 'Moderate'],
    ['Set up', 'set-up', 'To establish or arrange.', 'स्थापित करना / व्यवस्था करना', '', 'They set up a new company.', 'खड़ा करना', 'Establish', 'Destroy', 'Easy'],
    ['Take over', 'take-over', 'To take control.', 'अधिकार में लेना / कार्यभार संभालना', '', 'The new manager will take over next week.', 'ऊपर से लेना = नियंत्रण लेना', 'Control', 'Yield', 'Moderate'],
    ['Work out', 'work-out', 'To exercise or solve a problem.', 'व्यायाम करना / हल निकालना', '', 'I try to work out at the gym twice a week.', 'काम करके बाहर निकालना = हल करना', 'Exercise, Solve', '', 'Easy']
];

$stmt = $pdo->prepare("INSERT IGNORE INTO phrasal_verbs (phrasal_verb, slug, english_meaning, hindi_meaning, explanation, example_sentence, memory_trick, synonyms, antonyms, difficulty, status, category_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Published', 2)");
foreach ($phrasals as $p) {
    $stmt->execute([$p[0], $p[1], $p[2], $p[3], $p[4], $p[5], $p[6], $p[7], $p[8], $p[9]]);
}

// Practice Questions (Sample of 10)
$questions = [
    ['idiom', 'What is the meaning of "Break the ice"?', 'To freeze water', 'To start a conversation', 'To break rules', 'To be very cold', 'B', 'It means to relieve tension and initiate conversation.', 'इसका अर्थ है बातचीत शुरू करना।'],
    ['idiom', 'If someone says a task is a "piece of cake", they mean:', 'It is delicious', 'It involves baking', 'It is very easy', 'It is very expensive', 'C', 'A piece of cake refers to a task that can be accomplished very easily.', ''],
    ['phrasal_verb', 'Choose the correct meaning for "Call off":', 'To call someone on phone', 'To postpone', 'To cancel', 'To shout loudly', 'C', 'Call off means to cancel an event.', 'रद्द करना।'],
    ['phrasal_verb', 'He had to ____ the meeting because of illness. (postpone)', 'call off', 'put off', 'take off', 'give off', 'B', 'Put off means to postpone.', ''],
    ['general', 'What does "Bite the bullet" mean?', 'To eat quickly', 'To shoot a gun', 'To face a difficult situation', 'To hide', 'C', 'Bite the bullet means to endure a painful situation.', ''],
    ['general', 'Never ____ your dreams. (stop trying)', 'give up', 'give in', 'give out', 'give away', 'A', 'Give up means to stop trying.', 'हार मानना।'],
    ['idiom', 'What does "Spill the beans" mean?', 'Drop food', 'Cook beans', 'Keep a secret', 'Reveal a secret', 'D', 'It means to reveal secret information unintentionally or intentionally.', ''],
    ['idiom', 'The new car cost an ____.', 'eye and a nose', 'arm and a leg', 'hand and a foot', 'ear and a tooth', 'B', 'Cost an arm and a leg means very expensive.', ''],
    ['phrasal_verb', 'My car ____ on the way to work. (stopped working)', 'broke up', 'broke out', 'broke down', 'broke into', 'C', 'Break down refers to machinery stopping working.', ''],
    ['phrasal_verb', 'I am ____ to the holidays. (anticipating)', 'looking after', 'looking into', 'looking forward to', 'looking up', 'C', 'Look forward to means anticipating something eagerly.', '']
];

$stmt = $pdo->prepare("INSERT INTO practice_questions (content_type, question, option_a, option_b, option_c, option_d, correct_answer, explanation, hindi_explanation) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
foreach ($questions as $q) {
    $stmt->execute([$q[0], $q[1], $q[2], $q[3], $q[4], $q[5], $q[6], $q[7], $q[8]]);
}

echo "Seeded Successfully!";
