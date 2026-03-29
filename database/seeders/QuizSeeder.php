<?php

namespace Database\Seeders;

use App\Models\QuizQuestion;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\School;
use Illuminate\Database\Seeder;

class QuizSeeder extends Seeder
{
    public function run(): void
    {
        $school = School::first();
        if (!$school) { $this->command->warn('No school found. Skipping.'); return; }

        $classes  = SchoolClass::where('school_id', $school->id)->get();
        $subjects = Subject::whereIn('id', function ($q) use ($school) {
            // pick distinct subject names (avoid duplicate Tamil/English/Maths entries)
            $q->selectRaw('MIN(id)')
              ->from('subjects')
              ->where('school_id', $school->id)
              ->groupBy('name');
        })->get();

        if ($classes->isEmpty() || $subjects->isEmpty()) {
            $this->command->warn('No classes or subjects found.');
            return;
        }

        $inserted = 0;

        foreach ($classes as $class) {
            // Determine class level for difficulty
            $level = $this->classLevel($class->name);

            foreach ($subjects as $subject) {
                // Skip if already seeded for this class+subject
                if (QuizQuestion::where('class_id', $class->id)
                                ->where('subject_id', $subject->id)
                                ->where('school_id', $school->id)
                                ->exists()) {
                    $this->command->line("  Skipping {$class->name} / {$subject->name} (already seeded)");
                    continue;
                }

                $questions = $this->getQuestions($subject->name, $level);

                foreach ($questions as $q) {
                    QuizQuestion::create([
                        'school_id'      => $school->id,
                        'class_id'       => $class->id,
                        'subject_id'     => $subject->id,
                        'question'       => $q['q'],
                        'option_a'       => $q['a'],
                        'option_b'       => $q['b'],
                        'option_c'       => $q['c'],
                        'option_d'       => $q['d'],
                        'correct_answer' => $q['ans'],
                        'explanation'    => $q['exp'] ?? null,
                        'difficulty'     => $q['diff'] ?? 'Medium',
                        'status'         => 'Active',
                    ]);
                    $inserted++;
                }

                $this->command->info("  ✓ {$class->name} / {$subject->name} — " . count($questions) . " questions");
            }
        }

        $this->command->info("Quiz seeder complete — {$inserted} questions inserted.");
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function classLevel(string $name): string
    {
        $name = strtolower(trim($name));
        if (in_array($name, ['nursery', 'lkg', 'ukg'])) return 'nursery';
        $num = (int) filter_var($name, FILTER_SANITIZE_NUMBER_INT);
        if ($num >= 1  && $num <= 3)  return 'primary_low';
        if ($num >= 4  && $num <= 6)  return 'primary_high';
        if ($num >= 7  && $num <= 9)  return 'middle';
        if ($num >= 10 && $num <= 12) return 'secondary';
        return 'primary_low';
    }

    private function getQuestions(string $subjectName, string $level): array
    {
        $name = strtolower(trim($subjectName));
        if (str_contains($name, 'math') || str_contains($name, 'maths')) {
            return $this->mathsQuestions($level);
        }
        if (str_contains($name, 'tamil')) {
            return $this->tamilQuestions($level);
        }
        if (str_contains($name, 'english')) {
            return $this->englishQuestions($level);
        }
        if (str_contains($name, 'science') || str_contains($name, 'physics') || str_contains($name, 'chemistry') || str_contains($name, 'biology')) {
            return $this->scienceQuestions($level);
        }
        // Default: general knowledge questions
        return $this->generalQuestions($level);
    }

    // ── MATHS QUESTIONS ───────────────────────────────────────────────────────

    private function mathsQuestions(string $level): array
    {
        $easy = [
            ['q'=>'What is 2 + 2?','a'=>'3','b'=>'4','c'=>'5','d'=>'6','ans'=>'B','exp'=>'2 + 2 = 4','diff'=>'Easy'],
            ['q'=>'What is 5 - 3?','a'=>'1','b'=>'2','c'=>'3','d'=>'4','ans'=>'B','exp'=>'5 - 3 = 2','diff'=>'Easy'],
            ['q'=>'What is 3 × 3?','a'=>'6','b'=>'8','c'=>'9','d'=>'12','ans'=>'C','exp'=>'3 × 3 = 9','diff'=>'Easy'],
            ['q'=>'What is 10 ÷ 2?','a'=>'3','b'=>'4','c'=>'5','d'=>'6','ans'=>'C','exp'=>'10 ÷ 2 = 5','diff'=>'Easy'],
            ['q'=>'Which number comes after 9?','a'=>'8','b'=>'10','c'=>'11','d'=>'7','ans'=>'B','exp'=>'After 9 comes 10','diff'=>'Easy'],
            ['q'=>'How many sides does a triangle have?','a'=>'2','b'=>'3','c'=>'4','d'=>'5','ans'=>'B','exp'=>'A triangle has 3 sides','diff'=>'Easy'],
            ['q'=>'What is 4 + 6?','a'=>'9','b'=>'10','c'=>'11','d'=>'12','ans'=>'B','exp'=>'4 + 6 = 10','diff'=>'Easy'],
            ['q'=>'What is 8 - 5?','a'=>'2','b'=>'3','c'=>'4','d'=>'5','ans'=>'B','exp'=>'8 - 5 = 3','diff'=>'Easy'],
            ['q'=>'What is 2 × 5?','a'=>'7','b'=>'8','c'=>'9','d'=>'10','ans'=>'D','exp'=>'2 × 5 = 10','diff'=>'Easy'],
            ['q'=>'What is 6 ÷ 2?','a'=>'2','b'=>'3','c'=>'4','d'=>'5','ans'=>'B','exp'=>'6 ÷ 2 = 3','diff'=>'Easy'],
            ['q'=>'How many sides does a square have?','a'=>'3','b'=>'4','c'=>'5','d'=>'6','ans'=>'B','exp'=>'A square has 4 sides','diff'=>'Easy'],
            ['q'=>'What is 1 + 1?','a'=>'1','b'=>'2','c'=>'3','d'=>'4','ans'=>'B','exp'=>'1 + 1 = 2','diff'=>'Easy'],
            ['q'=>'What is 7 - 4?','a'=>'2','b'=>'3','c'=>'4','d'=>'5','ans'=>'B','exp'=>'7 - 4 = 3','diff'=>'Easy'],
            ['q'=>'What is 5 × 2?','a'=>'7','b'=>'8','c'=>'9','d'=>'10','ans'=>'D','exp'=>'5 × 2 = 10','diff'=>'Easy'],
            ['q'=>'What is 9 ÷ 3?','a'=>'2','b'=>'3','c'=>'4','d'=>'5','ans'=>'B','exp'=>'9 ÷ 3 = 3','diff'=>'Easy'],
            ['q'=>'Which is the largest single digit number?','a'=>'7','b'=>'8','c'=>'9','d'=>'6','ans'=>'C','exp'=>'9 is the largest single digit number','diff'=>'Easy'],
            ['q'=>'What is 3 + 5?','a'=>'7','b'=>'8','c'=>'9','d'=>'10','ans'=>'B','exp'=>'3 + 5 = 8','diff'=>'Easy'],
            ['q'=>'How many zeros are in one hundred?','a'=>'1','b'=>'2','c'=>'3','d'=>'0','ans'=>'B','exp'=>'100 has 2 zeros','diff'=>'Easy'],
            ['q'=>'What is half of 10?','a'=>'4','b'=>'5','c'=>'6','d'=>'7','ans'=>'B','exp'=>'Half of 10 is 5','diff'=>'Easy'],
            ['q'=>'What shape has no corners?','a'=>'Square','b'=>'Triangle','c'=>'Circle','d'=>'Rectangle','ans'=>'C','exp'=>'A circle has no corners','diff'=>'Easy'],
            ['q'=>'What is 4 × 4?','a'=>'12','b'=>'14','c'=>'16','d'=>'18','ans'=>'C','exp'=>'4 × 4 = 16','diff'=>'Easy'],
            ['q'=>'What is 20 - 7?','a'=>'11','b'=>'12','c'=>'13','d'=>'14','ans'=>'C','exp'=>'20 - 7 = 13','diff'=>'Easy'],
            ['q'=>'What is 3 + 3 + 3?','a'=>'6','b'=>'7','c'=>'8','d'=>'9','ans'=>'D','exp'=>'3 + 3 + 3 = 9','diff'=>'Easy'],
            ['q'=>'What is 15 ÷ 5?','a'=>'2','b'=>'3','c'=>'4','d'=>'5','ans'=>'B','exp'=>'15 ÷ 5 = 3','diff'=>'Easy'],
            ['q'=>'What comes before 20?','a'=>'18','b'=>'19','c'=>'21','d'=>'22','ans'=>'B','exp'=>'19 comes before 20','diff'=>'Easy'],
        ];

        $medium = [
            ['q'=>'What is 12 × 12?','a'=>'124','b'=>'132','c'=>'144','d'=>'156','ans'=>'C','exp'=>'12 × 12 = 144','diff'=>'Medium'],
            ['q'=>'What is the value of π (pi) approximately?','a'=>'2.14','b'=>'3.14','c'=>'4.14','d'=>'1.14','ans'=>'B','exp'=>'π ≈ 3.14159','diff'=>'Medium'],
            ['q'=>'What is the perimeter of a square with side 5 cm?','a'=>'10 cm','b'=>'15 cm','c'=>'20 cm','d'=>'25 cm','ans'=>'C','exp'=>'Perimeter = 4 × side = 4 × 5 = 20 cm','diff'=>'Medium'],
            ['q'=>'What is 25% of 200?','a'=>'25','b'=>'40','c'=>'50','d'=>'75','ans'=>'C','exp'=>'25% of 200 = (25/100) × 200 = 50','diff'=>'Medium'],
            ['q'=>'What is the area of a rectangle 6 cm × 4 cm?','a'=>'20 cm²','b'=>'24 cm²','c'=>'28 cm²','d'=>'32 cm²','ans'=>'B','exp'=>'Area = length × breadth = 6 × 4 = 24 cm²','diff'=>'Medium'],
            ['q'=>'What is LCM of 4 and 6?','a'=>'8','b'=>'10','c'=>'12','d'=>'24','ans'=>'C','exp'=>'LCM(4,6) = 12','diff'=>'Medium'],
            ['q'=>'What is the HCF of 12 and 18?','a'=>'3','b'=>'6','c'=>'9','d'=>'12','ans'=>'B','exp'=>'HCF(12,18) = 6','diff'=>'Medium'],
            ['q'=>'Which of these is a prime number?','a'=>'9','b'=>'15','c'=>'17','d'=>'21','ans'=>'C','exp'=>'17 is divisible only by 1 and itself','diff'=>'Medium'],
            ['q'=>'What is 2³?','a'=>'6','b'=>'8','c'=>'9','d'=>'12','ans'=>'B','exp'=>'2³ = 2 × 2 × 2 = 8','diff'=>'Medium'],
            ['q'=>'What is the square root of 144?','a'=>'11','b'=>'12','c'=>'13','d'=>'14','ans'=>'B','exp'=>'√144 = 12','diff'=>'Medium'],
            ['q'=>'What is 15% of 100?','a'=>'10','b'=>'12','c'=>'15','d'=>'20','ans'=>'C','exp'=>'15% of 100 = 15','diff'=>'Medium'],
            ['q'=>'What is 3/4 as a decimal?','a'=>'0.50','b'=>'0.70','c'=>'0.75','d'=>'0.80','ans'=>'C','exp'=>'3 ÷ 4 = 0.75','diff'=>'Medium'],
            ['q'=>'What is the sum of angles in a triangle?','a'=>'90°','b'=>'180°','c'=>'270°','d'=>'360°','ans'=>'B','exp'=>'Sum of angles in a triangle = 180°','diff'=>'Medium'],
            ['q'=>'What is 100 × 0?','a'=>'0','b'=>'1','c'=>'10','d'=>'100','ans'=>'A','exp'=>'Any number multiplied by 0 is 0','diff'=>'Medium'],
            ['q'=>'What is the next prime after 7?','a'=>'8','b'=>'9','c'=>'10','d'=>'11','ans'=>'D','exp'=>'11 is the next prime after 7','diff'=>'Medium'],
            ['q'=>'A dozen equals how many?','a'=>'10','b'=>'11','c'=>'12','d'=>'13','ans'=>'C','exp'=>'A dozen = 12','diff'=>'Medium'],
            ['q'=>'What is 5² + 3²?','a'=>'28','b'=>'30','c'=>'34','d'=>'36','ans'=>'C','exp'=>'5² + 3² = 25 + 9 = 34','diff'=>'Medium'],
            ['q'=>'What is the area of a circle with radius 7? (π=22/7)','a'=>'144 cm²','b'=>'154 cm²','c'=>'164 cm²','d'=>'174 cm²','ans'=>'B','exp'=>'Area = πr² = (22/7) × 49 = 154 cm²','diff'=>'Medium'],
            ['q'=>'How many degrees in a right angle?','a'=>'45°','b'=>'60°','c'=>'90°','d'=>'120°','ans'=>'C','exp'=>'A right angle is 90°','diff'=>'Medium'],
            ['q'=>'What is 1000 ÷ 25?','a'=>'30','b'=>'35','c'=>'40','d'=>'45','ans'=>'C','exp'=>'1000 ÷ 25 = 40','diff'=>'Medium'],
            ['q'=>'What is the value of x if 2x = 14?','a'=>'5','b'=>'6','c'=>'7','d'=>'8','ans'=>'C','exp'=>'2x = 14 → x = 7','diff'=>'Medium'],
            ['q'=>'What is 0.5 + 0.5?','a'=>'0.5','b'=>'1','c'=>'1.5','d'=>'5','ans'=>'B','exp'=>'0.5 + 0.5 = 1.0','diff'=>'Medium'],
            ['q'=>'What is the perimeter of a rectangle 8 cm × 3 cm?','a'=>'11 cm','b'=>'22 cm','c'=>'24 cm','d'=>'32 cm','ans'=>'B','exp'=>'Perimeter = 2(l+b) = 2(8+3) = 22 cm','diff'=>'Medium'],
            ['q'=>'Which fraction is largest: 1/2, 1/3, 1/4?','a'=>'1/4','b'=>'1/3','c'=>'1/2','d'=>'All equal','ans'=>'C','exp'=>'1/2 = 0.5 is greater than 1/3 and 1/4','diff'=>'Medium'],
            ['q'=>'What is 7 × 8?','a'=>'54','b'=>'56','c'=>'58','d'=>'60','ans'=>'B','exp'=>'7 × 8 = 56','diff'=>'Medium'],
        ];

        $hard = [
            ['q'=>'If x² - 5x + 6 = 0, what are the values of x?','a'=>'2 and 3','b'=>'1 and 6','c'=>'-2 and -3','d'=>'3 and 4','ans'=>'A','exp'=>'x² - 5x + 6 = (x-2)(x-3) = 0, so x = 2 or 3','diff'=>'Hard'],
            ['q'=>'What is sin 30°?','a'=>'0','b'=>'1/2','c'=>'√3/2','d'=>'1','ans'=>'B','exp'=>'sin 30° = 1/2','diff'=>'Hard'],
            ['q'=>'What is cos 60°?','a'=>'0','b'=>'1/2','c'=>'√3/2','d'=>'1','ans'=>'B','exp'=>'cos 60° = 1/2','diff'=>'Hard'],
            ['q'=>'What is the derivative of x²?','a'=>'x','b'=>'2x','c'=>'x²','d'=>'2','ans'=>'B','exp'=>'d/dx(x²) = 2x','diff'=>'Hard'],
            ['q'=>'What is tan 45°?','a'=>'0','b'=>'1/2','c'=>'1','d'=>'√3','ans'=>'C','exp'=>'tan 45° = 1','diff'=>'Hard'],
            ['q'=>'What is the sum of first 10 natural numbers?','a'=>'45','b'=>'55','c'=>'65','d'=>'75','ans'=>'B','exp'=>'Sum = n(n+1)/2 = 10×11/2 = 55','diff'=>'Hard'],
            ['q'=>'What is the quadratic formula?','a'=>'x = -b ± √(b²-4ac) / 2a','b'=>'x = b ± √(b²+4ac) / 2a','c'=>'x = -b ± √(b²-4ac) / a','d'=>'x = b ± √(b²-4ac) / a','ans'=>'A','exp'=>'The quadratic formula is x = (-b ± √(b²-4ac)) / 2a','diff'=>'Hard'],
            ['q'=>'What is 2¹⁰?','a'=>'512','b'=>'1024','c'=>'2048','d'=>'256','ans'=>'B','exp'=>'2¹⁰ = 1024','diff'=>'Hard'],
            ['q'=>'A circle has circumference 44 cm. What is its radius? (π=22/7)','a'=>'5 cm','b'=>'6 cm','c'=>'7 cm','d'=>'8 cm','ans'=>'C','exp'=>'C = 2πr → 44 = 2×(22/7)×r → r = 7 cm','diff'=>'Hard'],
            ['q'=>'What is the probability of getting heads in a coin toss?','a'=>'1/4','b'=>'1/3','c'=>'1/2','d'=>'2/3','ans'=>'C','exp'=>'P(heads) = 1/2 as there are 2 equally likely outcomes','diff'=>'Hard'],
            ['q'=>'If 3x + 7 = 22, what is x?','a'=>'3','b'=>'4','c'=>'5','d'=>'6','ans'=>'C','exp'=>'3x = 22 - 7 = 15, x = 5','diff'=>'Hard'],
            ['q'=>'What is the volume of a cube with side 4 cm?','a'=>'48 cm³','b'=>'56 cm³','c'=>'64 cm³','d'=>'72 cm³','ans'=>'C','exp'=>'Volume = side³ = 4³ = 64 cm³','diff'=>'Hard'],
            ['q'=>'What is √2 approximately?','a'=>'1.21','b'=>'1.41','c'=>'1.61','d'=>'1.81','ans'=>'B','exp'=>'√2 ≈ 1.414','diff'=>'Hard'],
            ['q'=>'What is the arithmetic mean of 5, 10, 15, 20, 25?','a'=>'12','b'=>'13','c'=>'15','d'=>'17','ans'=>'C','exp'=>'Mean = (5+10+15+20+25)/5 = 75/5 = 15','diff'=>'Hard'],
            ['q'=>'How many diagonals does a hexagon have?','a'=>'6','b'=>'9','c'=>'12','d'=>'15','ans'=>'B','exp'=>'Diagonals = n(n-3)/2 = 6(3)/2 = 9','diff'=>'Hard'],
            ['q'=>'What is the compound interest on ₹1000 at 10% for 2 years?','a'=>'₹200','b'=>'₹210','c'=>'₹220','d'=>'₹230','ans'=>'B','exp'=>'CI = P[(1+r/100)^n - 1] = 1000[(1.1)²-1] = ₹210','diff'=>'Hard'],
            ['q'=>'If a = 3, b = 4, what is a² + b²?','a'=>'7','b'=>'25','c'=>'12','d'=>'49','ans'=>'B','exp'=>'a² + b² = 9 + 16 = 25','diff'=>'Hard'],
            ['q'=>'What is log₁₀(1000)?','a'=>'2','b'=>'3','c'=>'4','d'=>'10','ans'=>'B','exp'=>'log₁₀(1000) = log₁₀(10³) = 3','diff'=>'Hard'],
            ['q'=>'What is the slope of the line y = 3x + 5?','a'=>'5','b'=>'3','c'=>'-3','d'=>'0','ans'=>'B','exp'=>'In y = mx + c, the slope m = 3','diff'=>'Hard'],
            ['q'=>'What is the distance between points (0,0) and (3,4)?','a'=>'4','b'=>'5','c'=>'6','d'=>'7','ans'=>'B','exp'=>'Distance = √(3²+4²) = √(9+16) = √25 = 5','diff'=>'Hard'],
            ['q'=>'What is sin²θ + cos²θ?','a'=>'0','b'=>'1/2','c'=>'1','d'=>'2','ans'=>'C','exp'=>'sin²θ + cos²θ = 1 (Pythagorean identity)','diff'=>'Hard'],
            ['q'=>'What is the value of 0! (zero factorial)?','a'=>'0','b'=>'1','c'=>'10','d'=>'Undefined','ans'=>'B','exp'=>'By definition, 0! = 1','diff'=>'Hard'],
            ['q'=>'What is the AP: 2, 5, 8, 11 ... common difference?','a'=>'2','b'=>'3','c'=>'4','d'=>'5','ans'=>'B','exp'=>'Common difference = 5-2 = 3','diff'=>'Hard'],
            ['q'=>'In a right triangle, if hypotenuse = 10, one side = 6, find the other side.','a'=>'6','b'=>'7','c'=>'8','d'=>'9','ans'=>'C','exp'=>'By Pythagoras: other = √(10²-6²) = √(100-36) = √64 = 8','diff'=>'Hard'],
            ['q'=>'What is the next term in GP: 2, 6, 18, 54, ...?','a'=>'108','b'=>'162','c'=>'216','d'=>'270','ans'=>'B','exp'=>'Common ratio = 3, so next = 54 × 3 = 162','diff'=>'Hard'],
        ];

        return $this->build100($easy, $medium, $hard, $level);
    }

    // ── ENGLISH QUESTIONS ─────────────────────────────────────────────────────

    private function englishQuestions(string $level): array
    {
        $easy = [
            ['q'=>'Which is a vowel?','a'=>'B','b'=>'C','c'=>'A','d'=>'D','ans'=>'C','exp'=>'A, E, I, O, U are vowels','diff'=>'Easy'],
            ['q'=>'How many letters are in the English alphabet?','a'=>'24','b'=>'25','c'=>'26','d'=>'27','ans'=>'C','exp'=>'The English alphabet has 26 letters','diff'=>'Easy'],
            ['q'=>'Which word is a noun?','a'=>'Run','b'=>'Happy','c'=>'Apple','d'=>'Quickly','ans'=>'C','exp'=>'Apple is the name of a thing — a noun','diff'=>'Easy'],
            ['q'=>'What is the opposite of "hot"?','a'=>'Warm','b'=>'Cold','c'=>'Cool','d'=>'Mild','ans'=>'B','exp'=>'The opposite (antonym) of hot is cold','diff'=>'Easy'],
            ['q'=>'Choose the correct spelling:','a'=>'Elefant','b'=>'Elephant','c'=>'Eliphant','d'=>'Elephent','ans'=>'B','exp'=>'The correct spelling is Elephant','diff'=>'Easy'],
            ['q'=>'What is a word that describes a noun called?','a'=>'Verb','b'=>'Adverb','c'=>'Adjective','d'=>'Pronoun','ans'=>'C','exp'=>'An adjective describes a noun','diff'=>'Easy'],
            ['q'=>'Which sentence is correct?','a'=>'She go to school.','b'=>'She goes to school.','c'=>'She going to school.','d'=>'She goed to school.','ans'=>'B','exp'=>'She (singular) takes "goes" in present tense','diff'=>'Easy'],
            ['q'=>'What is the plural of "child"?','a'=>'Childs','b'=>'Childes','c'=>'Children','d'=>'Childrens','ans'=>'C','exp'=>'The irregular plural of child is children','diff'=>'Easy'],
            ['q'=>'Which word means "happy"?','a'=>'Sad','b'=>'Angry','c'=>'Joyful','d'=>'Tired','ans'=>'C','exp'=>'Joyful is a synonym for happy','diff'=>'Easy'],
            ['q'=>'A ___ of fish is called:','a'=>'Herd','b'=>'Flock','c'=>'School','d'=>'Pack','ans'=>'C','exp'=>'A group of fish is called a school','diff'=>'Easy'],
            ['q'=>'What punctuation ends a question?','a'=>'.','b'=>'!','c'=>',','d'=>'?','ans'=>'D','exp'=>'A question mark (?) ends a question','diff'=>'Easy'],
            ['q'=>'Which is a verb in "The dog runs fast"?','a'=>'Dog','b'=>'Runs','c'=>'Fast','d'=>'The','ans'=>'B','exp'=>'Runs is the action word (verb) in the sentence','diff'=>'Easy'],
            ['q'=>'What is the past tense of "eat"?','a'=>'Eated','b'=>'Eaten','c'=>'Ate','d'=>'Eats','ans'=>'C','exp'=>'The past tense of eat is ate','diff'=>'Easy'],
            ['q'=>'Fill in the blank: I ___ a student.','a'=>'are','b'=>'is','c'=>'am','d'=>'be','ans'=>'C','exp'=>'I + am (first person singular)','diff'=>'Easy'],
            ['q'=>'How many vowels are in "ORANGE"?','a'=>'2','b'=>'3','c'=>'4','d'=>'5','ans'=>'B','exp'=>'O, A, E are the vowels in ORANGE','diff'=>'Easy'],
            ['q'=>'Which word is an adjective in "a big red ball"?','a'=>'a','b'=>'big','c'=>'ball','d'=>'and','ans'=>'B','exp'=>'Big (and red) are adjectives describing the ball','diff'=>'Easy'],
            ['q'=>'What is the full form of "don\'t"?','a'=>'do not','b'=>'does not','c'=>'did not','d'=>'doing not','ans'=>'A','exp'=>"Don't is a contraction of 'do not'",'diff'=>'Easy'],
            ['q'=>'The ___ barked loudly. Fill in with the right word.','a'=>'cat','b'=>'dog','c'=>'bird','d'=>'fish','ans'=>'B','exp'=>'Dogs bark, so "the dog barked" is correct','diff'=>'Easy'],
            ['q'=>'What is the opposite of "big"?','a'=>'Large','b'=>'Huge','c'=>'Small','d'=>'Tall','ans'=>'C','exp'=>'The antonym of big is small','diff'=>'Easy'],
            ['q'=>'Which of these is a proper noun?','a'=>'city','b'=>'river','c'=>'London','d'=>'mountain','ans'=>'C','exp'=>'London is a proper noun — name of a specific place','diff'=>'Easy'],
            ['q'=>'How many consonants are there in the English alphabet?','a'=>'19','b'=>'20','c'=>'21','d'=>'22','ans'=>'C','exp'=>'26 letters - 5 vowels = 21 consonants','diff'=>'Easy'],
            ['q'=>'What is the singular of "teeth"?','a'=>'Tooths','b'=>'Tooth','c'=>'Teeths','d'=>'Teether','ans'=>'B','exp'=>'The singular of teeth is tooth','diff'=>'Easy'],
            ['q'=>'Choose the correct article: ___ apple a day keeps the doctor away.','a'=>'A','b'=>'An','c'=>'The','d'=>'No article','ans'=>'B','exp'=>'"An" is used before words starting with a vowel sound','diff'=>'Easy'],
            ['q'=>'Which of these is a preposition?','a'=>'Run','b'=>'Under','c'=>'Beautiful','d'=>'Quickly','ans'=>'B','exp'=>'"Under" shows the position/relation — it is a preposition','diff'=>'Easy'],
            ['q'=>'What does "synonym" mean?','a'=>'Opposite word','b'=>'Same meaning word','c'=>'Rhyming word','d'=>'Describing word','ans'=>'B','exp'=>'A synonym is a word with the same or similar meaning','diff'=>'Easy'],
        ];

        $medium = [
            ['q'=>'Identify the tense: "She has been reading for two hours."','a'=>'Past Perfect','b'=>'Present Continuous','c'=>'Present Perfect Continuous','d'=>'Past Continuous','ans'=>'C','exp'=>'"Has been + V-ing" indicates Present Perfect Continuous tense','diff'=>'Medium'],
            ['q'=>'Choose the correct passive voice: "The teacher teaches the students."','a'=>'The students are taught by the teacher.','b'=>'The students were taught by the teacher.','c'=>'The students have taught by the teacher.','d'=>'The students be taught by teacher.','ans'=>'A','exp'=>'Active to Passive: Object + is/are + V3 + by + Subject','diff'=>'Medium'],
            ['q'=>'What figure of speech is used in "The wind whispered through the trees"?','a'=>'Simile','b'=>'Metaphor','c'=>'Personification','d'=>'Alliteration','ans'=>'C','exp'=>'Giving human qualities (whispering) to non-human things is personification','diff'=>'Medium'],
            ['q'=>'Choose the correct form: Neither the boys nor the girl ___ present.','a'=>'were','b'=>'are','c'=>'was','d'=>'is','ans'=>'C','exp'=>'With "neither...nor", the verb agrees with the nearest subject (girl - singular)','diff'=>'Medium'],
            ['q'=>'What is the synonym of "eloquent"?','a'=>'Dull','b'=>'Articulate','c'=>'Confused','d'=>'Silent','ans'=>'B','exp'=>'Eloquent means well-spoken/articulate','diff'=>'Medium'],
            ['q'=>'Choose the correctly punctuated sentence:','a'=>"It's a beautiful day",'b'=>'Its a beautiful day','c'=>'Its a beautiful day.','d'=>"Its' a beautiful day",'ans'=>'A','exp'=>"It's = It is (needs apostrophe for contraction)",'diff'=>'Medium'],
            ['q'=>'What type of sentence is: "What a beautiful sunset!"?','a'=>'Declarative','b'=>'Interrogative','c'=>'Imperative','d'=>'Exclamatory','ans'=>'D','exp'=>'Sentences expressing strong emotion end with ! and are exclamatory','diff'=>'Medium'],
            ['q'=>'Fill in: He ___ to school yesterday.','a'=>'go','b'=>'goes','c'=>'went','d'=>'gone','ans'=>'C','exp'=>'"Yesterday" indicates past tense. Past of go is went.','diff'=>'Medium'],
            ['q'=>'What is an oxymoron?','a'=>'Repetition of a sound','b'=>'Exaggeration','c'=>'Two contradictory words together','d'=>'Comparison using like/as','ans'=>'C','exp'=>'An oxymoron puts two contradictory words together, e.g. "deafening silence"','diff'=>'Medium'],
            ['q'=>'Identify the clause type: "Although it was raining, they played outside."','a'=>'Simple sentence','b'=>'Compound sentence','c'=>'Complex sentence','d'=>'Compound-complex','ans'=>'C','exp'=>'A complex sentence has an independent + dependent clause ("Although it was raining")','diff'=>'Medium'],
            ['q'=>'What is the antonym of "benevolent"?','a'=>'Generous','b'=>'Kind','c'=>'Malevolent','d'=>'Gentle','ans'=>'C','exp'=>'Benevolent means kind; its antonym is malevolent (wishing harm)','diff'=>'Medium'],
            ['q'=>'He is good ___ mathematics. Choose the right preposition.','a'=>'in','b'=>'at','c'=>'on','d'=>'for','ans'=>'B','exp'=>'The correct idiom is "good at" a subject','diff'=>'Medium'],
            ['q'=>'What is an alliteration?','a'=>'Comparison using like/as','b'=>'Repetition of initial consonant sounds','c'=>'Human qualities to non-human','d'=>'Exaggeration for effect','ans'=>'B','exp'=>'Alliteration repeats the same consonant sound: "Peter Piper picked..."','diff'=>'Medium'],
            ['q'=>'Choose the indirect speech: He said, "I am happy."','a'=>'He said that he is happy.','b'=>'He said that he was happy.','c'=>'He said that I was happy.','d'=>'He told that he was happy.','ans'=>'B','exp'=>'In indirect speech, "am" changes to "was" (backshift of tense)','diff'=>'Medium'],
            ['q'=>'Which word is misspelled?','a'=>'Necessary','b'=>'Recomend','c'=>'Receive','d'=>'Achieve','ans'=>'B','exp'=>'The correct spelling is "Recommend" (double m)','diff'=>'Medium'],
            ['q'=>'What is the noun form of "beautiful"?','a'=>'Beautify','b'=>'Beauty','c'=>'Beautifully','d'=>'Beautification','ans'=>'B','exp'=>'The noun form of the adjective "beautiful" is "beauty"','diff'=>'Medium'],
            ['q'=>'A word that replaces a noun is called:','a'=>'Verb','b'=>'Adverb','c'=>'Pronoun','d'=>'Adjective','ans'=>'C','exp'=>'A pronoun (he, she, it, they) replaces a noun','diff'=>'Medium'],
            ['q'=>'What is a metaphor?','a'=>'Comparison using like/as','b'=>'Direct comparison without like/as','c'=>'Giving life to non-living things','d'=>'Sound repetition','ans'=>'B','exp'=>'A metaphor directly compares: "Time is money"','diff'=>'Medium'],
            ['q'=>'Choose the right conjunction: I was tired, ___ I kept working.','a'=>'because','b'=>'or','c'=>'but','d'=>'so','ans'=>'C','exp'=>'"But" shows contrast between being tired and continuing to work','diff'=>'Medium'],
            ['q'=>'What is the superlative form of "good"?','a'=>'Gooder','b'=>'Better','c'=>'Best','d'=>'Most good','ans'=>'C','exp'=>'Good → Better → Best (irregular comparative/superlative)','diff'=>'Medium'],
            ['q'=>'Fill in: She ___ her homework before dinner.','a'=>'finish','b'=>'finishes','c'=>'had finished','d'=>'will finish','ans'=>'C','exp'=>'"Before dinner" indicates past event; "had finished" is past perfect','diff'=>'Medium'],
            ['q'=>'What type of noun is "happiness"?','a'=>'Proper noun','b'=>'Collective noun','c'=>'Material noun','d'=>'Abstract noun','ans'=>'D','exp'=>'Happiness is a feeling you cannot touch — it\'s an abstract noun','diff'=>'Medium'],
            ['q'=>'Which is correct: "I saw ___ one-eyed man."','a'=>'a','b'=>'an','c'=>'the','d'=>'no article','ans'=>'A','exp'=>'"One" starts with a consonant sound (w), so use "a"','diff'=>'Medium'],
            ['q'=>'What is a homophone pair?','a'=>'big/small','b'=>'their/there','c'=>'run/runs','d'=>'fast/slow','ans'=>'B','exp'=>'Homophones sound the same but have different meanings: their/there','diff'=>'Medium'],
            ['q'=>'Identify the literary device in "Her voice was music to my ears."','a'=>'Simile','b'=>'Metaphor','c'=>'Personification','d'=>'Hyperbole','ans'=>'B','exp'=>'Comparing voice to music without "like" or "as" is a metaphor','diff'=>'Medium'],
        ];

        $hard = [
            ['q'=>'What is an "iamb" in poetry?','a'=>'Two stressed syllables','b'=>'An unstressed followed by a stressed syllable','c'=>'Three syllables','d'=>'A rhyming couplet','ans'=>'B','exp'=>'An iamb is a metrical foot: unstressed + stressed syllable (da-DUM)','diff'=>'Hard'],
            ['q'=>'What literary term describes a reference to a well-known work, person, or event?','a'=>'Allusion','b'=>'Illusion','c'=>'Allegory','d'=>'Anachronism','ans'=>'A','exp'=>'An allusion is an indirect reference to something outside the text','diff'=>'Hard'],
            ['q'=>'What is the subjunctive mood?','a'=>'Commands','b'=>'Hypothetical or wishful situations','c'=>'Questions','d'=>'Simple facts','ans'=>'B','exp'=>'Subjunctive expresses wishes, conditions, hypotheticals: "If I were you..."','diff'=>'Hard'],
            ['q'=>'What is zeugma?','a'=>'Repetition at sentence start','b'=>'One word governs two others in different ways','c'=>'Understatement','d'=>'Reversal of grammatical structures','ans'=>'B','exp'=>'"She broke his car and his heart" — broke governs both car and heart differently','diff'=>'Hard'],
            ['q'=>'What is the difference between "affect" and "effect"?','a'=>'No difference','b'=>'Affect is usually a verb; effect is usually a noun','c'=>'Effect is usually a verb; affect is usually a noun','d'=>'Both are only nouns','ans'=>'B','exp'=>'Affect = verb (to influence); Effect = noun (result)','diff'=>'Hard'],
            ['q'=>'What is an anachronism?','a'=>'A flashback','b'=>'Something placed in wrong time period','c'=>'A type of rhyme','d'=>'A figure of speech','ans'=>'B','exp'=>'Anachronism = placing something in a historical period where it does not belong','diff'=>'Hard'],
            ['q'=>'Identify: "The pen is mightier than the sword."','a'=>'Simile','b'=>'Metaphor','c'=>'Alliteration','d'=>'Oxymoron','ans'=>'B','exp'=>'Comparing pen to sword without "like/as" is a metaphor','diff'=>'Hard'],
            ['q'=>'What is a "soliloquy"?','a'=>'A conversation between two characters','b'=>'A speech given by a character alone on stage','c'=>'A song in a play','d'=>'An introduction to a play','ans'=>'B','exp'=>'A soliloquy is when a character speaks thoughts aloud when alone','diff'=>'Hard'],
            ['q'=>'What does "denouement" mean in literature?','a'=>'The rising action','b'=>'The climax','c'=>'The final resolution of a plot','d'=>'The introduction','ans'=>'C','exp'=>'Denouement (French) = the unraveling/resolution after the climax','diff'=>'Hard'],
            ['q'=>'Which is an example of synecdoche?','a'=>'"The wind sang"','b'=>'"All hands on deck"','c'=>'"Brave as a lion"','d'=>'"It was raining cats and dogs"','ans'=>'B','exp'=>'"All hands" (part) is used to mean all people (whole) — synecdoche','diff'=>'Hard'],
            ['q'=>'What is the correct use of "whom"?','a'=>'Whom is calling?','b'=>'To whom did you speak?','c'=>'Whom called you?','d'=>'Whom is there?','ans'=>'B','exp'=>'"Whom" is used as an object; "To whom did you speak?" is correct','diff'=>'Hard'],
            ['q'=>'What is the term for a word derived from a person\'s name?','a'=>'Acronym','b'=>'Eponym','c'=>'Homonym','d'=>'Synonym','ans'=>'B','exp'=>'An eponym is a word derived from a name, e.g., "sandwich" from Earl of Sandwich','diff'=>'Hard'],
            ['q'=>'In grammar, what is a "gerund"?','a'=>'A verb ending in -ing used as a noun','b'=>'A verb ending in -ed','c'=>'An adjective form of a verb','d'=>'A conditional verb','ans'=>'A','exp'=>'A gerund is an -ing form used as a noun: "Swimming is fun"','diff'=>'Hard'],
            ['q'=>'What rhetorical device repeats a word at the end of successive clauses?','a'=>'Anaphora','b'=>'Epistrophe','c'=>'Chiasmus','d'=>'Asyndeton','ans'=>'B','exp'=>'Epistrophe repeats words at clause ends: "government of the people, by the people, for the people"','diff'=>'Hard'],
            ['q'=>'What is "stream of consciousness" in literature?','a'=>'A dramatic monologue','b'=>'A narrative technique presenting characters\' thoughts continuously','c'=>'A type of sonnet','d'=>'A plot device','ans'=>'B','exp'=>'Stream of consciousness presents the unfiltered flow of thoughts (Virginia Woolf, James Joyce)','diff'=>'Hard'],
            ['q'=>'Which sentence uses the Oxford comma correctly?','a'=>'I need eggs, milk and bread.','b'=>'I need eggs, milk, and bread.','c'=>'I need, eggs, milk and bread.','d'=>'I need eggs milk, and bread.','ans'=>'B','exp'=>'The Oxford comma appears before the final "and" in a list','diff'=>'Hard'],
            ['q'=>'What is a "portmanteau" word?','a'=>'A borrowed word from French','b'=>'A word blending sounds and meanings of two words','c'=>'An archaic word','d'=>'A technical term','ans'=>'B','exp'=>'Portmanteau blends two words: smoke + fog = smog','diff'=>'Hard'],
            ['q'=>'What is the term for when a speaker addresses an absent or imaginary person?','a'=>'Apostrophe','b'=>'Assonance','c'=>'Anaphora','d'=>'Allegory','ans'=>'A','exp'=>'Apostrophe (literary device) addresses someone absent: "O Death, where is thy sting?"','diff'=>'Hard'],
            ['q'=>'What is a "Bildungsroman"?','a'=>'A mystery novel','b'=>'A coming-of-age story','c'=>'A war narrative','d'=>'A political allegory','ans'=>'B','exp'=>'Bildungsroman (German) = a coming-of-age novel tracing moral/psychological growth','diff'=>'Hard'],
            ['q'=>'What is "polysyndeton"?','a'=>'Omission of conjunctions','b'=>'Use of many conjunctions in close succession','c'=>'Repetition of vowel sounds','d'=>'A type of metaphor','ans'=>'B','exp'=>'Polysyndeton uses many conjunctions: "I came and I saw and I conquered"','diff'=>'Hard'],
            ['q'=>'Which part of speech is "nevertheless"?','a'=>'Adjective','b'=>'Preposition','c'=>'Conjunctive adverb','d'=>'Coordinating conjunction','ans'=>'C','exp'=>'"Nevertheless" is a conjunctive adverb showing contrast','diff'=>'Hard'],
            ['q'=>'What is dramatic irony?','a'=>'When the audience knows something characters do not','b'=>'When a character says the opposite of what they mean','c'=>'An unexpected plot twist','d'=>'Exaggeration for comic effect','ans'=>'A','exp'=>'Dramatic irony occurs when the audience has more information than the characters','diff'=>'Hard'],
            ['q'=>'What is the "unreliable narrator"?','a'=>'A narrator who speaks too fast','b'=>'A narrator whose credibility is compromised','c'=>'A third-person narrator','d'=>'An omniscient narrator','ans'=>'B','exp'=>'An unreliable narrator gives a biased or inaccurate account of events','diff'=>'Hard'],
            ['q'=>'What is "catharsis" in drama?','a'=>'The climax of a play','b'=>'Emotional purging or release experienced by the audience','c'=>'The tragic hero\'s flaw','d'=>'A type of soliloquy','ans'=>'B','exp'=>'Catharsis (Aristotle) = purging of pity and fear through watching tragedy','diff'=>'Hard'],
            ['q'=>'What is the term for a poem of 14 lines in iambic pentameter?','a'=>'Ode','b'=>'Haiku','c'=>'Sonnet','d'=>'Ballad','ans'=>'C','exp'=>'A sonnet has 14 lines, typically in iambic pentameter (Shakespeare, Petrarch)','diff'=>'Hard'],
        ];

        return $this->build100($easy, $medium, $hard, $level);
    }

    // ── TAMIL QUESTIONS ───────────────────────────────────────────────────────

    private function tamilQuestions(string $level): array
    {
        $easy = [
            ['q'=>'தமிழ் எழுத்துக்கள் எத்தனை?','a'=>'247','b'=>'216','c'=>'256','d'=>'236','ans'=>'A','exp'=>'தமிழில் 247 எழுத்துக்கள் உள்ளன (12 உயிர் + 18 மெய் + 1 ஆய்தம் = 247 உயிர்மெய்)','diff'=>'Easy'],
            ['q'=>'உயிர் எழுத்துக்கள் எத்தனை?','a'=>'10','b'=>'12','c'=>'18','d'=>'14','ans'=>'B','exp'=>'தமிழில் 12 உயிர் எழுத்துக்கள் உள்ளன','diff'=>'Easy'],
            ['q'=>'மெய் எழுத்துக்கள் எத்தனை?','a'=>'12','b'=>'16','c'=>'18','d'=>'20','ans'=>'C','exp'=>'தமிழில் 18 மெய் எழுத்துக்கள் உள்ளன','diff'=>'Easy'],
            ['q'=>'"வானம்" என்ற சொல்லின் பொருள் என்ன?','a'=>'நிலம்','b'=>'கடல்','c'=>'ஆகாயம்','d'=>'மலை','ans'=>'C','exp'=>'வானம் = ஆகாயம் (sky)','diff'=>'Easy'],
            ['q'=>'தமிழ் மொழியின் தாய் யார்?','a'=>'அகத்தியர்','b'=>'திருவள்ளுவர்','c'=>'கம்பர்','d'=>'சுந்தரர்','ans'=>'A','exp'=>'தமிழ் மொழியை வடிவமைத்தவர் அகத்தியர் என கருதப்படுகிறார்','diff'=>'Easy'],
            ['q'=>'"அன்னை" என்ற சொல் யாரை குறிக்கும்?','a'=>'அக்கா','b'=>'அம்மா','c'=>'தங்கை','d'=>'பாட்டி','ans'=>'B','exp'=>'அன்னை = தாய் = அம்மா (mother)','diff'=>'Easy'],
            ['q'=>'குறள் எழுதியவர் யார்?','a'=>'கம்பர்','b'=>'இளங்கோ','c'=>'திருவள்ளுவர்','d'=>'கபிலர்','ans'=>'C','exp'=>'திருக்குறளை எழுதியவர் திருவள்ளுவர்','diff'=>'Easy'],
            ['q'=>'"பால்" என்பது எந்த உணவு வகை?','a'=>'திட உணவு','b'=>'திரவ உணவு','c'=>'காய்கறி','d'=>'பழம்','ans'=>'B','exp'=>'பால் ஒரு திரவ உணவு (liquid food)','diff'=>'Easy'],
            ['q'=>'ஆய்த எழுத்து எத்தனை?','a'=>'2','b'=>'1','c'=>'3','d'=>'0','ans'=>'B','exp'=>'ஆய்த எழுத்து ஒன்றே ஒன்று (ஃ)','diff'=>'Easy'],
            ['q'=>'"நீர்" என்பதற்கு ஆங்கிலத்தில் என்ன?','a'=>'Fire','b'=>'Air','c'=>'Water','d'=>'Earth','ans'=>'C','exp'=>'நீர் = Water','diff'=>'Easy'],
            ['q'=>'தமிழ் நாட்டின் தலைநகர் எது?','a'=>'மதுரை','b'=>'கோயம்புத்தூர்','c'=>'சென்னை','d'=>'திருச்சி','ans'=>'C','exp'=>'தமிழ் நாட்டின் தலைநகர் சென்னை','diff'=>'Easy'],
            ['q'=>'"புத்தகம்" என்பதற்கு ஆங்கிலத்தில் என்ன?','a'=>'Pen','b'=>'Book','c'=>'Table','d'=>'Chair','ans'=>'B','exp'=>'புத்தகம் = Book','diff'=>'Easy'],
            ['q'=>'சூரிய ஒளி கொடுப்பது எது?','a'=>'நிலவு','b'=>'நட்சத்திரம்','c'=>'சூரியன்','d'=>'மேகம்','ans'=>'C','exp'=>'சூரியன் ஒளி கொடுக்கிறது','diff'=>'Easy'],
            ['q'=>'"மரம்" என்பதன் பொருள் என்ன?','a'=>'மண்','b'=>'கல்','c'=>'Tree','d'=>'நீர்','ans'=>'C','exp'=>'மரம் = Tree','diff'=>'Easy'],
            ['q'=>'இந்தியாவின் தேசிய பறவை எது?','a'=>'கொக்கு','b'=>'மயில்','c'=>'காகம்','d'=>'கழுகு','ans'=>'B','exp'=>'இந்தியாவின் தேசிய பறவை மயில்','diff'=>'Easy'],
            ['q'=>'"பூ" என்பதற்கு ஆங்கிலத்தில் என்ன?','a'=>'Fruit','b'=>'Leaf','c'=>'Flower','d'=>'Tree','ans'=>'C','exp'=>'பூ = Flower','diff'=>'Easy'],
            ['q'=>'தமிழ் இலக்கணம் எத்தனை பிரிவுகளை கொண்டது?','a'=>'3','b'=>'4','c'=>'5','d'=>'6','ans'=>'C','exp'=>'தமிழ் இலக்கணம் 5 பிரிவுகளை கொண்டது: எழுத்து, சொல், பொருள், யாப்பு, அணி','diff'=>'Easy'],
            ['q'=>'"வீடு" என்பதற்கு ஆங்கிலத்தில் என்ன?','a'=>'School','b'=>'House','c'=>'Shop','d'=>'Temple','ans'=>'B','exp'=>'வீடு = House','diff'=>'Easy'],
            ['q'=>'திருக்குறளில் எத்தனை அதிகாரங்கள் உள்ளன?','a'=>'108','b'=>'128','c'=>'133','d'=>'133','ans'=>'C','exp'=>'திருக்குறளில் 133 அதிகாரங்கள் உள்ளன','diff'=>'Easy'],
            ['q'=>'"தாய்மொழி" என்பதன் பொருள்?','a'=>'தாய் பேசும் மொழி','b'=>'பிறந்த நாட்டின் மொழி','c'=>'அலுவல் மொழி','d'=>'கற்ற மொழி','ans'=>'A','exp'=>'தாய்மொழி = mother tongue = பிறப்பிலிருந்தே கற்ற மொழி','diff'=>'Easy'],
            ['q'=>'எத்தனை உயிர்மெய் எழுத்துக்கள் உள்ளன?','a'=>'200','b'=>'216','c'=>'236','d'=>'246','ans'=>'B','exp'=>'12 உயிர் × 18 மெய் = 216 உயிர்மெய் எழுத்துக்கள்','diff'=>'Easy'],
            ['q'=>'"அறம்" என்பதன் பொருள்?','a'=>'Wealth','b'=>'Pleasure','c'=>'Virtue/Righteousness','d'=>'War','ans'=>'C','exp'=>'அறம் = நீதி, தர்மம் (virtue/righteousness)','diff'=>'Easy'],
            ['q'=>'கம்பராமாயணம் எழுதியவர் யார்?','a'=>'திருவள்ளுவர்','b'=>'கம்பர்','c'=>'இளங்கோ','d'=>'நக்கீரர்','ans'=>'B','exp'=>'கம்பராமாயணத்தை கம்பர் எழுதினார்','diff'=>'Easy'],
            ['q'=>'"மழை" என்பதற்கு ஆங்கிலத்தில் என்ன?','a'=>'Sun','b'=>'Wind','c'=>'Rain','d'=>'Cloud','ans'=>'C','exp'=>'மழை = Rain','diff'=>'Easy'],
            ['q'=>'சிலப்பதிகாரம் எழுதியவர்?','a'=>'கம்பர்','b'=>'திருவள்ளுவர்','c'=>'இளங்கோ அடிகள்','d'=>'மணிமேகலை','ans'=>'C','exp'=>'சிலப்பதிகாரம் இளங்கோ அடிகளால் எழுதப்பட்டது','diff'=>'Easy'],
        ];

        $medium = [
            ['q'=>'திருக்குறளில் எத்தனை குறள்கள் உள்ளன?','a'=>'1000','b'=>'1330','c'=>'1008','d'=>'999','ans'=>'B','exp'=>'திருக்குறளில் 1330 குறள்கள் உள்ளன','diff'=>'Medium'],
            ['q'=>'திருக்குறள் எத்தனை பால்களை கொண்டது?','a'=>'2','b'=>'3','c'=>'4','d'=>'5','ans'=>'B','exp'=>'அறத்துப்பால், பொருட்பால், காமத்துப்பால் என மூன்று பால்கள்','diff'=>'Medium'],
            ['q'=>'தமிழ் இலக்கியத்தில் "சங்க இலக்கியம்" என்பது எந்த காலத்தை சேர்ந்தது?','a'=>'கி.மு 300 - கி.பி 300','b'=>'கி.பி 100 - 500','c'=>'கி.மு 100 - 200','d'=>'கி.பி 500 - 1000','ans'=>'A','exp'=>'சங்க இலக்கியம் கி.மு 300 முதல் கி.பி 300 வரை இயற்றப்பட்டது','diff'=>'Medium'],
            ['q'=>'"வேர்ச்சொல்" என்றால் என்ன?','a'=>'வினை சொல்','b'=>'சொல்லின் மூலரூபம்','c'=>'பெயர்ச்சொல்','d'=>'விளி','ans'=>'B','exp'=>'வேர்ச்சொல் என்பது ஒரு சொல்லின் மூல வடிவம்','diff'=>'Medium'],
            ['q'=>'தொல்காப்பியம் யாரால் எழுதப்பட்டது?','a'=>'கம்பர்','b'=>'திருவள்ளுவர்','c'=>'தொல்காப்பியர்','d'=>'அகத்தியர்','ans'=>'C','exp'=>'தமிழின் முதல் இலக்கண நூல் தொல்காப்பியம்; தொல்காப்பியரால் எழுதப்பட்டது','diff'=>'Medium'],
            ['q'=>'அகப்பொருள் இலக்கியத்தில் மலர்ந்த நிலம் என்ன?','a'=>'குறிஞ்சி','b'=>'முல்லை','c'=>'மருதம்','d'=>'நெய்தல்','ans'=>'A','exp'=>'குறிஞ்சி (மலை நிலம்) இணைதல் மற்றும் இனிமையை குறிக்கும்','diff'=>'Medium'],
            ['q'=>'இலக்கணத்தில் "பன்மை" என்றால் என்ன?','a'=>'ஒரு பொருள்','b'=>'இரண்டு பொருள்','c'=>'பல பொருள்கள்','d'=>'எதுவும் இல்லை','ans'=>'C','exp'=>'பன்மை = Plural (பல பொருள்களை குறிக்கும்)','diff'=>'Medium'],
            ['q'=>'நன்னூல் யாரால் எழுதப்பட்டது?','a'=>'பவணந்தி முனிவர்','b'=>'தொல்காப்பியர்','c'=>'திருவள்ளுவர்','d'=>'இளங்கோ','ans'=>'A','exp'=>'நன்னூல் என்ற இலக்கண நூலை பவணந்தி முனிவர் இயற்றினார்','diff'=>'Medium'],
            ['q'=>'அணி இலக்கணத்தில் "உவமை அணி" என்றால்?','a'=>'உவமை இல்லாமல் ஒப்பிடுவது','b'=>'இரண்டு பொருட்களை ஒப்பிட "போல்" "என" போன்ற சொற்களை பயன்படுத்துவது','c'=>'ஒரு பொருளை மட்டும் விவரிப்பது','d'=>'எதிர்மறை கூறுவது','ans'=>'B','exp'=>'உவமை அணி = Simile; போல், என, ஆல் போன்ற சொற்கள் பயன்படும்','diff'=>'Medium'],
            ['q'=>'தமிழில் "ஏதுவமை" என்பது ஆங்கிலத்தில்?','a'=>'Metaphor','b'=>'Personification','c'=>'Simile','d'=>'Alliteration','ans'=>'C','exp'=>'ஏதுவமை = Simile (like/as போன்று ஒப்பிடுவது)','diff'=>'Medium'],
            ['q'=>'இலக்கணத்தில் "ஆண்பால்" என்றால்?','a'=>'Feminine gender','b'=>'Neuter gender','c'=>'Masculine gender','d'=>'Common gender','ans'=>'C','exp'=>'ஆண்பால் = Masculine gender','diff'=>'Medium'],
            ['q'=>'சங்க இலக்கியத்தில் "எட்டுத்தொகை" என்பது எத்தனை நூல்களின் தொகுப்பு?','a'=>'6','b'=>'7','c'=>'8','d'=>'9','ans'=>'C','exp'=>'எட்டுத்தொகை = 8 நூல்களின் தொகுப்பு','diff'=>'Medium'],
            ['q'=>'புறநானூறு எந்த வகை இலக்கியம்?','a'=>'அகம்','b'=>'புறம்','c'=>'இரண்டும்','d'=>'ஏதும் இல்லை','ans'=>'B','exp'=>'புறநானூறு புறப்பொருள் (வீரம், போர், கொடை) பற்றிய நூல்','diff'=>'Medium'],
            ['q'=>'தமிழில் வினைமுற்று என்பது?','a'=>'முற்றுப்பெறாத வினை','b'=>'முற்றுப்பெற்ற வினை','c'=>'பெயரை விளிக்கும் சொல்','d'=>'வேர்ச்சொல்','ans'=>'B','exp'=>'வினைமுற்று என்பது செயல் நிறைவுற்றதை காட்டும் வினைச்சொல்','diff'=>'Medium'],
            ['q'=>'மணிமேகலை யாரால் எழுதப்பட்டது?','a'=>'இளங்கோ அடிகள்','b'=>'சீத்தலை சாத்தனார்','c'=>'திருவள்ளுவர்','d'=>'கம்பர்','ans'=>'B','exp'=>'மணிமேகலை சீத்தலை சாத்தனாரால் இயற்றப்பட்டது','diff'=>'Medium'],
            ['q'=>'தமிழில் "காலம்" எத்தனை வகைப்படும்?','a'=>'2','b'=>'3','c'=>'4','d'=>'5','ans'=>'B','exp'=>'இறந்தகாலம், நிகழ்காலம், எதிர்காலம் என மூன்று காலங்கள்','diff'=>'Medium'],
            ['q'=>'தமிழ் நாட்டின் தேசிய மலர் எது?','a'=>'தாமரை','b'=>'ஆம்பல்','c'=>'முல்லை','d'=>'செவ்வரளி','ans'=>'A','exp'=>'இந்தியாவின் தேசிய மலர் தாமரை','diff'=>'Medium'],
            ['q'=>'பரிபாடல் எந்த தொகையை சேர்ந்தது?','a'=>'பத்துப்பாட்டு','b'=>'எட்டுத்தொகை','c'=>'பதினெண்கீழ்க்கணக்கு','d'=>'பதினெண்மேற்கணக்கு','ans'=>'B','exp'=>'பரிபாடல் எட்டுத்தொகை நூல்களில் ஒன்று','diff'=>'Medium'],
            ['q'=>'தமிழில் "ஈற்று ஒற்று" என்றால் என்ன?','a'=>'சொல்லின் முதல் எழுத்து','b'=>'சொல்லின் கடைசி எழுத்து','c'=>'சொல்லின் நடு எழுத்து','d'=>'சொல் இணைப்பு','ans'=>'B','exp'=>'ஈற்று ஒற்று = சொல்லின் இறுதியில் வரும் மெய் எழுத்து','diff'=>'Medium'],
            ['q'=>'திருக்குறளில் "அறத்துப்பால்" எத்தனை அதிகாரங்களை கொண்டது?','a'=>'25','b'=>'38','c'=>'40','d'=>'33','ans'=>'B','exp'=>'அறத்துப்பால் 38 அதிகாரங்களை கொண்டது','diff'=>'Medium'],
            ['q'=>'"நேரிசை வெண்பா" என்பது எத்தனை அடிகளை கொண்டது?','a'=>'2','b'=>'3','c'=>'4','d'=>'5','ans'=>'C','exp'=>'நேரிசை வெண்பா 4 அடிகளை (வரிகளை) கொண்டது','diff'=>'Medium'],
            ['q'=>'சங்க காலத்தில் "ஐந்திணை" என்பது எத்தனை நிலங்களை குறிக்கும்?','a'=>'3','b'=>'4','c'=>'5','d'=>'6','ans'=>'C','exp'=>'குறிஞ்சி, முல்லை, மருதம், நெய்தல், பாலை என ஐந்து நிலங்கள்','diff'=>'Medium'],
            ['q'=>'தொல்காப்பியத்தில் எத்தனை அதிகாரங்கள் உள்ளன?','a'=>'3','b'=>'5','c'=>'7','d'=>'9','ans'=>'A','exp'=>'தொல்காப்பியத்தில் எழுத்ததிகாரம், சொல்லதிகாரம், பொருளதிகாரம் என மூன்று அதிகாரங்கள்','diff'=>'Medium'],
            ['q'=>'"கலித்தொகை" எந்த வகை யாப்பில் உள்ளது?','a'=>'வெண்பா','b'=>'ஆசிரியப்பா','c'=>'கலிப்பா','d'=>'வஞ்சிப்பா','ans'=>'C','exp'=>'கலித்தொகை கலிப்பாவில் எழுதப்பட்ட சங்க நூல்','diff'=>'Medium'],
        ];

        $hard = [
            ['q'=>'தமிழில் "யாப்பு இலக்கணம்" எத்தனை வகை அளவிணைகளை கொண்டது?','a'=>'3','b'=>'4','c'=>'5','d'=>'6','ans'=>'C','exp'=>'நேர், நிரை, நேர்நிரை, நிரைநேர், நேர்நேர் என 5 அளவிணைகள்','diff'=>'Hard'],
            ['q'=>'சிலப்பதிகாரத்தில் கோவலன் மனைவி பெயர் என்ன?','a'=>'மதவி','b'=>'கண்ணகி','c'=>'மணிமேகலை','d'=>'ஆதிமந்தி','ans'=>'B','exp'=>'கோவலனின் மனைவி கண்ணகி; சிலப்பதிகாரத்தின் நாயகி','diff'=>'Hard'],
            ['q'=>'தமிழ் இலக்கணத்தில் "உரிச்சொல்" என்பது எதை குறிக்கும்?','a'=>'குறிப்பிட்ட நிலத்திற்குரிய சொல்','b'=>'ஆங்கிலத்திலிருந்து வந்த சொல்','c'=>'உயிர் எழுத்துடன் தொடங்கும் சொல்','d'=>'எண்களை குறிக்கும் சொல்','ans'=>'A','exp'=>'உரிச்சொல் என்பது அகப்பொருள் இலக்கியத்தில் குறிப்பிட்ட திணைக்கு உரிய உணர்வை குறிக்கும் சொல்','diff'=>'Hard'],
            ['q'=>'"அந்தாதி" என்னும் யாப்பு முறை என்ன?','a'=>'ஒவ்வொரு பாடலும் முந்தைய பாடலின் கடைசி சொல்லில் தொடங்கும்','b'=>'அனைத்து வரிகளும் ஒரே எழுத்தில் முடியும்','c'=>'ஒரே சொல் திரும்ப திரும்ப வரும்','d'=>'4 அடிகளை கொண்ட பாடல்','ans'=>'A','exp'=>'அந்தாதி = முன் பாடல் இறுதி, அடுத்த பாடல் முதலாக வருவது','diff'=>'Hard'],
            ['q'=>'திருவள்ளுவர் தினம் எந்த தேதி கொண்டாடப்படுகிறது?','a'=>'ஜனவரி 15','b'=>'ஜனவரி 16','c'=>'ஜனவரி 14','d'=>'ஜனவரி 17','ans'=>'A','exp'=>'திருவள்ளுவர் தினம் தை மாதம் 2ம் நாள் (ஜனவரி 15 அல்லது 16)','diff'=>'Hard'],
            ['q'=>'சங்க இலக்கியத்தில் "பத்துப்பாட்டு" என்பது எத்தனை நூல்களின் தொகுப்பு?','a'=>'8','b'=>'9','c'=>'10','d'=>'12','ans'=>'C','exp'=>'பத்துப்பாட்டு 10 நூல்களின் தொகுப்பு','diff'=>'Hard'],
            ['q'=>'தமிழில் "மோனை" என்றால் என்ன?','a'=>'இறுதி எழுத்துக்கள் ஒத்திருப்பது','b'=>'முதல் எழுத்துக்கள் ஒத்திருப்பது','c'=>'நடு எழுத்துக்கள் ஒத்திருப்பது','d'=>'முழு சொல்லும் ஒத்திருப்பது','ans'=>'B','exp'=>'மோனை = ஆதி மோனை = வரியின் முதல் எழுத்துக்கள் ஒத்திருப்பது (Alliteration)','diff'=>'Hard'],
            ['q'=>'"இரட்டைக்கிளவி" என்பது?','a'=>'இரண்டு சொற்கள் இணைந்து ஒரு பொருள் தரும்','b'=>'ஒரு சொல் இரண்டு பொருள் தரும்','c'=>'இரண்டு சொற்களும் ஒரே மாதிரி ஒலிக்கும்','d'=>'இரண்டு சொற்களும் ஒரே பொருளில் வரும்','ans'=>'A','exp'=>'இரட்டைக்கிளவி = இரண்டு சொற்களும் சேர்ந்து ஒரு பொருளை தரும், eg: மரம்செடி','diff'=>'Hard'],
            ['q'=>'சங்க காலத்தில் "கபிலர்" எந்த நூலை எழுதினார்?','a'=>'குறிஞ்சிப்பாட்டு','b'=>'பதிற்றுப்பத்து','c'=>'மலைபடுகடாம்','d'=>'புறநானூறு','ans'=>'A','exp'=>'கபிலர் குறிஞ்சிப்பாட்டை இயற்றினார்','diff'=>'Hard'],
            ['q'=>'திருக்குறளில் "காமத்துப்பால்" எத்தனை அதிகாரங்களை கொண்டது?','a'=>'20','b'=>'25','c'=>'30','d'=>'45','ans'=>'B','exp'=>'காமத்துப்பால் 25 அதிகாரங்களை கொண்டது','diff'=>'Hard'],
            ['q'=>'"ஆசிரியப்பா" என்னும் யாப்பில் சீர்களின் குறைந்தபட்ச அளவு என்ன?','a'=>'3 சீர்','b'=>'4 சீர்','c'=>'முறையற்ற அளவு','d'=>'3 அடி','ans'=>'C','exp'=>'ஆசிரியப்பா = சீர்களின் அளவு முறையற்று வரலாம் (variable)','diff'=>'Hard'],
            ['q'=>'நம்மாழ்வார் இயற்றிய நூல் எது?','a'=>'திருவாசகம்','b'=>'திருவாய்மொழி','c'=>'திருமுறை','d'=>'திருப்புகழ்','ans'=>'B','exp'=>'நம்மாழ்வார் திருவாய்மொழியை இயற்றினார்','diff'=>'Hard'],
            ['q'=>'மாணிக்கவாசகர் இயற்றிய நூல் எது?','a'=>'திருவாசகம்','b'=>'திருவாய்மொழி','c'=>'திருப்புகழ்','d'=>'தேவாரம்','ans'=>'A','exp'=>'மாணிக்கவாசகர் திருவாசகம் என்ற சிவபக்தி நூலை இயற்றினார்','diff'=>'Hard'],
            ['q'=>'"முத்தொள்ளாயிரம்" என்பது எத்தனை பாடல்களின் தொகுப்பு?','a'=>'300','b'=>'900','c'=>'1000','d'=>'108','ans'=>'A','exp'=>'முத்தொள்ளாயிரம் = 300 பாடல்களின் தொகுப்பு (சேரர், சோழர், பாண்டியர் 100 பாடல் தலா)','diff'=>'Hard'],
            ['q'=>'தமிழ் இலக்கியத்தில் "புராணம்" என்பது?','a'=>'இதிகாசம்','b'=>'சரித்திர நூல்','c'=>'கடவுளின் கதைகளை சொல்லும் நூல்','d'=>'அகத்தியர் நூல்','ans'=>'C','exp'=>'புராணம் = கடவுளரின் கதைகளை விவரிக்கும் நூல்','diff'=>'Hard'],
            ['q'=>'"குறுந்தொகை" எத்தனை பாடல்களை கொண்டது?','a'=>'200','b'=>'400','c'=>'401','d'=>'500','ans'=>'C','exp'=>'குறுந்தொகை 401 பாடல்களை கொண்டது','diff'=>'Hard'],
            ['q'=>'கம்பராமாயணத்தில் எத்தனை காண்டங்கள் உள்ளன?','a'=>'5','b'=>'6','c'=>'7','d'=>'8','ans'=>'B','exp'=>'கம்பராமாயணத்தில் 6 காண்டங்கள் உள்ளன','diff'=>'Hard'],
            ['q'=>'"அகநானூறு" என்னும் நூலில் எத்தனை பாடல்கள் உள்ளன?','a'=>'300','b'=>'400','c'=>'500','d'=>'600','ans'=>'B','exp'=>'அகநானூறு 400 பாடல்களை கொண்ட சங்க இலக்கிய நூல்','diff'=>'Hard'],
            ['q'=>'"சிலப்பதிகாரம்" என்பது என்ன வகை இலக்கியம்?','a'=>'காவியம்','b'=>'சிறுகதை','c'=>'நாடகம்','d'=>'கவிதை','ans'=>'A','exp'=>'சிலப்பதிகாரம் ஒரு காவியம் (Epic)','diff'=>'Hard'],
            ['q'=>'தமிழ் இலக்கணத்தில் "வல்லினம்" என்னும் மெய்கள் யாவை?','a'=>'ங ஞ ண ந ம ய','b'=>'க ச ட த ப ற','c'=>'ய ர ல வ ழ ள','d'=>'ம ண ந ன','ans'=>'B','exp'=>'வல்லினம் = க ச ட த ப ற (6 எழுத்துக்கள்)','diff'=>'Hard'],
            ['q'=>'"நான்மணிக்கடிகை" என்னும் நூல் எந்த வகை?','a'=>'பதினெண்மேற்கணக்கு','b'=>'பதினெண்கீழ்க்கணக்கு','c'=>'எட்டுத்தொகை','d'=>'பத்துப்பாட்டு','ans'=>'B','exp'=>'நான்மணிக்கடிகை பதினெண்கீழ்க்கணக்கு நூல்களில் ஒன்று','diff'=>'Hard'],
            ['q'=>'"ஔவையார்" இயற்றிய நீதி நூல் எது?','a'=>'ஆத்திசூடி','b'=>'நான்மணிக்கடிகை','c'=>'திரிகடுகம்','d'=>'ஏலாதி','ans'=>'A','exp'=>'ஔவையார் ஆத்திசூடி மற்றும் கொன்றைவேந்தன் போன்ற நீதி நூல்களை இயற்றினார்','diff'=>'Hard'],
            ['q'=>'தமிழில் "இடையினம்" என்னும் மெய்கள் யாவை?','a'=>'க ச ட த ப ற','b'=>'ங ஞ ண ந ம ன','c'=>'ய ர ல வ ழ ள','d'=>'ய வ','ans'=>'C','exp'=>'இடையினம் = ய ர ல வ ழ ள (6 எழுத்துக்கள்)','diff'=>'Hard'],
            ['q'=>'சங்க இலக்கியத்தில் "களவு" என்பது?','a'=>'திருட்டு','b'=>'இரகசிய காதல்','c'=>'திருமண விழா','d'=>'பிரிவு வருத்தம்','ans'=>'B','exp'=>'களவு = மறைவான/இரகசிய காதல் (கற்பு என்பது திருமணத்திற்கு பிறகான காதல்)','diff'=>'Hard'],
        ];

        return $this->build100($easy, $medium, $hard, $level);
    }

    // ── SCIENCE QUESTIONS ─────────────────────────────────────────────────────

    private function scienceQuestions(string $level): array
    {
        $easy   = array_slice($this->scienceBank(), 0, 25);
        $medium = array_slice($this->scienceBank(), 25, 25);
        $hard   = array_slice($this->scienceBank(), 50, 25);
        return $this->build100($easy, $medium, $hard, $level);
    }

    private function scienceBank(): array
    {
        return [
            ['q'=>'What is the chemical symbol for water?','a'=>'WA','b'=>'H2O','c'=>'HO2','d'=>'O2H','ans'=>'B','exp'=>'Water is H₂O — 2 hydrogen atoms and 1 oxygen atom','diff'=>'Easy'],
            ['q'=>'Which planet is closest to the Sun?','a'=>'Venus','b'=>'Mars','c'=>'Mercury','d'=>'Earth','ans'=>'C','exp'=>'Mercury is the closest planet to the Sun','diff'=>'Easy'],
            ['q'=>'What gas do plants absorb during photosynthesis?','a'=>'Oxygen','b'=>'Nitrogen','c'=>'Carbon Dioxide','d'=>'Hydrogen','ans'=>'C','exp'=>'Plants absorb CO₂ and release O₂ during photosynthesis','diff'=>'Easy'],
            ['q'=>'What is the hardest natural substance?','a'=>'Gold','b'=>'Iron','c'=>'Diamond','d'=>'Platinum','ans'=>'C','exp'=>'Diamond scores 10 on the Mohs hardness scale','diff'=>'Easy'],
            ['q'=>'How many bones are in the adult human body?','a'=>'196','b'=>'206','c'=>'216','d'=>'226','ans'=>'B','exp'=>'An adult human body has 206 bones','diff'=>'Easy'],
            ['q'=>'Which organ pumps blood through the body?','a'=>'Lungs','b'=>'Liver','c'=>'Kidney','d'=>'Heart','ans'=>'D','exp'=>'The heart is the pump that circulates blood','diff'=>'Easy'],
            ['q'=>'What is the speed of light approximately?','a'=>'3 × 10⁵ km/s','b'=>'3 × 10⁶ km/s','c'=>'3 × 10⁴ km/s','d'=>'3 × 10³ km/s','ans'=>'A','exp'=>'Speed of light ≈ 3 × 10⁵ km/s = 300,000 km/s','diff'=>'Easy'],
            ['q'=>'Which is the largest organ of the human body?','a'=>'Liver','b'=>'Heart','c'=>'Brain','d'=>'Skin','ans'=>'D','exp'=>'Skin is the largest organ, covering the entire body','diff'=>'Easy'],
            ['q'=>'What force pulls objects toward Earth?','a'=>'Magnetism','b'=>'Gravity','c'=>'Friction','d'=>'Tension','ans'=>'B','exp'=>'Gravity is the force that attracts objects toward the Earth','diff'=>'Easy'],
            ['q'=>'What is the chemical symbol for gold?','a'=>'Gd','b'=>'Go','c'=>'Au','d'=>'Ag','ans'=>'C','exp'=>'Gold\'s symbol is Au (from Latin: Aurum)','diff'=>'Easy'],
            ['q'=>'How many chambers does the human heart have?','a'=>'2','b'=>'3','c'=>'4','d'=>'5','ans'=>'C','exp'=>'The human heart has 4 chambers: 2 atria + 2 ventricles','diff'=>'Easy'],
            ['q'=>'What is the process of a liquid turning to gas?','a'=>'Condensation','b'=>'Evaporation','c'=>'Sublimation','d'=>'Fusion','ans'=>'B','exp'=>'Evaporation is when liquid turns into gas/vapour','diff'=>'Easy'],
            ['q'=>'Which planet is known as the Red Planet?','a'=>'Venus','b'=>'Jupiter','c'=>'Mars','d'=>'Saturn','ans'=>'C','exp'=>'Mars appears red due to iron oxide on its surface','diff'=>'Easy'],
            ['q'=>'What is the powerhouse of the cell?','a'=>'Nucleus','b'=>'Ribosome','c'=>'Mitochondria','d'=>'Vacuole','ans'=>'C','exp'=>'Mitochondria produce ATP energy for the cell','diff'=>'Easy'],
            ['q'=>'What type of energy does the sun produce?','a'=>'Nuclear energy only','b'=>'Thermal energy only','c'=>'Light and heat energy','d'=>'Chemical energy','ans'=>'C','exp'=>'The Sun produces both light (electromagnetic) and heat (thermal) energy','diff'=>'Easy'],
            ['q'=>'Which gas is most abundant in Earth\'s atmosphere?','a'=>'Oxygen','b'=>'Nitrogen','c'=>'Carbon Dioxide','d'=>'Argon','ans'=>'B','exp'=>'Nitrogen makes up about 78% of Earth\'s atmosphere','diff'=>'Easy'],
            ['q'=>'What is the boiling point of water at sea level?','a'=>'90°C','b'=>'95°C','c'=>'100°C','d'=>'105°C','ans'=>'C','exp'=>'Water boils at 100°C (212°F) at standard atmospheric pressure','diff'=>'Easy'],
            ['q'=>'What is the centre of an atom called?','a'=>'Electron','b'=>'Proton','c'=>'Nucleus','d'=>'Neutron','ans'=>'C','exp'=>'The nucleus at the centre of an atom contains protons and neutrons','diff'=>'Easy'],
            ['q'=>'What is the unit of electric current?','a'=>'Volt','b'=>'Watt','c'=>'Ampere','d'=>'Ohm','ans'=>'C','exp'=>'Electric current is measured in Amperes (A)','diff'=>'Easy'],
            ['q'=>'Plants make their own food through:','a'=>'Respiration','b'=>'Photosynthesis','c'=>'Transpiration','d'=>'Germination','ans'=>'B','exp'=>'Photosynthesis uses sunlight, CO₂, and water to produce glucose','diff'=>'Easy'],
            ['q'=>'What is Newton\'s First Law of Motion?','a'=>'F = ma','b'=>'Every action has an equal and opposite reaction','c'=>'An object at rest stays at rest unless acted upon by a force','d'=>'Force equals mass times acceleration','ans'=>'C','exp'=>'Newton\'s 1st Law = Law of Inertia: objects resist changes in motion','diff'=>'Medium'],
            ['q'=>'What is the atomic number of Carbon?','a'=>'4','b'=>'6','c'=>'8','d'=>'12','ans'=>'B','exp'=>'Carbon has atomic number 6 (6 protons)','diff'=>'Medium'],
            ['q'=>'What is osmosis?','a'=>'Movement of solute from high to low concentration','b'=>'Movement of water through a semipermeable membrane','c'=>'Absorption of nutrients by plants','d'=>'Cell division','ans'=>'B','exp'=>'Osmosis = movement of water from low to high solute concentration through a semipermeable membrane','diff'=>'Medium'],
            ['q'=>'Which of these is not a conductor of electricity?','a'=>'Copper','b'=>'Iron','c'=>'Rubber','d'=>'Aluminium','ans'=>'C','exp'=>'Rubber is an insulator, not a conductor of electricity','diff'=>'Medium'],
            ['q'=>'What is the pH of pure water?','a'=>'5','b'=>'6','c'=>'7','d'=>'8','ans'=>'C','exp'=>'Pure water is neutral with a pH of 7','diff'=>'Medium'],
            ['q'=>'DNA stands for:','a'=>'Deoxyribonucleic Acid','b'=>'Dioxynucleic Acid','c'=>'Deoxynuclear Acid','d'=>'Diribonucleic Acid','ans'=>'A','exp'=>'DNA = Deoxyribonucleic Acid — carries genetic information','diff'=>'Medium'],
            ['q'=>'What is the SI unit of force?','a'=>'Joule','b'=>'Pascal','c'=>'Newton','d'=>'Watt','ans'=>'C','exp'=>'Force is measured in Newtons (N): F = ma','diff'=>'Medium'],
            ['q'=>'What is the chemical formula for common salt?','a'=>'KCl','b'=>'NaCl','c'=>'NaOH','d'=>'CaCO₃','ans'=>'B','exp'=>'Common salt = Sodium Chloride = NaCl','diff'=>'Medium'],
            ['q'=>'What type of bond involves sharing of electrons?','a'=>'Ionic bond','b'=>'Covalent bond','c'=>'Metallic bond','d'=>'Hydrogen bond','ans'=>'B','exp'=>'Covalent bonds involve sharing electron pairs between atoms','diff'=>'Medium'],
            ['q'=>'Which blood group is the universal donor?','a'=>'AB+','b'=>'O+','c'=>'A+','d'=>'O-','ans'=>'D','exp'=>'O- (O negative) is the universal donor for red blood cells','diff'=>'Medium'],
            ['q'=>'What is the law of conservation of energy?','a'=>'Energy can be created but not destroyed','b'=>'Energy cannot be created or destroyed, only transformed','c'=>'Energy is always lost as heat','d'=>'Energy and mass are unrelated','ans'=>'B','exp'=>'Energy cannot be created or destroyed — it only changes form','diff'=>'Hard'],
            ['q'=>'What is Avogadro\'s number?','a'=>'6.022 × 10²²','b'=>'6.022 × 10²³','c'=>'6.022 × 10²⁴','d'=>'6.022 × 10²¹','ans'=>'B','exp'=>'Avogadro\'s number = 6.022 × 10²³ particles per mole','diff'=>'Hard'],
            ['q'=>'What is the Heisenberg Uncertainty Principle?','a'=>'Energy is quantized','b'=>'Cannot know both position and momentum precisely simultaneously','c'=>'Light behaves as both wave and particle','d'=>'Electrons orbit in fixed paths','ans'=>'B','exp'=>'Heisenberg: Δx·Δp ≥ ℏ/2 — precision in position and momentum are inversely related','diff'=>'Hard'],
            ['q'=>'What does the Krebs Cycle produce?','a'=>'Glucose','b'=>'Oxygen','c'=>'ATP and CO₂','d'=>'Water and nitrogen','ans'=>'C','exp'=>'The Krebs cycle (citric acid cycle) produces ATP, CO₂, NADH, and FADH₂','diff'=>'Hard'],
            ['q'=>'What is the half-life of Carbon-14?','a'=>'2,730 years','b'=>'5,730 years','c'=>'11,460 years','d'=>'1,365 years','ans'=>'B','exp'=>'Carbon-14 has a half-life of approximately 5,730 years — used in radiocarbon dating','diff'=>'Hard'],
            ['q'=>'What is Bernoulli\'s Principle?','a'=>'Pressure increases as velocity increases','b'=>'Pressure decreases as velocity increases','c'=>'Pressure and velocity are unrelated','d'=>'Buoyancy equals displaced fluid weight','ans'=>'B','exp'=>'Bernoulli: In fluid flow, increased velocity leads to decreased pressure','diff'=>'Hard'],
            ['q'=>'What is the process where a solid turns directly into gas?','a'=>'Evaporation','b'=>'Condensation','c'=>'Sublimation','d'=>'Deposition','ans'=>'C','exp'=>'Sublimation: solid → gas without passing through liquid (e.g., dry ice)','diff'=>'Hard'],
            ['q'=>'What is the charge of a neutron?','a'=>'Positive','b'=>'Negative','c'=>'Neutral','d'=>'Variable','ans'=>'C','exp'=>'Neutrons have no electric charge; they are neutral particles','diff'=>'Hard'],
            ['q'=>'What is entropy?','a'=>'Potential energy stored','b'=>'Measure of disorder or randomness in a system','c'=>'Kinetic energy of molecules','d'=>'Bond energy','ans'=>'B','exp'=>'Entropy (S) measures the degree of disorder; it always increases in isolated systems (2nd Law of Thermodynamics)','diff'=>'Hard'],
            ['q'=>'What is the role of ribosomes in a cell?','a'=>'Store genetic information','b'=>'Produce energy','c'=>'Synthesize proteins','d'=>'Control cell division','ans'=>'C','exp'=>'Ribosomes read mRNA and synthesize proteins (translation)','diff'=>'Hard'],
            ['q'=>'What is the chemical formula for glucose?','a'=>'C₆H₁₂O₆','b'=>'C₆H₁₀O₅','c'=>'C₁₂H₂₂O₁₁','d'=>'CH₂O','ans'=>'A','exp'=>'Glucose = C₆H₁₂O₆ — the primary source of cellular energy','diff'=>'Hard'],
            ['q'=>'What is Ohm\'s Law?','a'=>'V = IR','b'=>'P = IV','c'=>'F = ma','d'=>'E = mc²','ans'=>'A','exp'=>'Ohm\'s Law: V = IR (Voltage = Current × Resistance)','diff'=>'Hard'],
            ['q'=>'What is the principle of superposition in physics?','a'=>'Waves can only travel in one medium','b'=>'When two waves overlap, the resultant is the sum of individual displacements','c'=>'Light bends around obstacles','d'=>'Every action has an equal and opposite reaction','ans'=>'B','exp'=>'Superposition: when waves meet, displacements add algebraically','diff'=>'Hard'],
            ['q'=>'Which type of electromagnetic radiation has the highest frequency?','a'=>'Radio waves','b'=>'Infrared','c'=>'X-rays','d'=>'Gamma rays','ans'=>'D','exp'=>'Gamma rays have the highest frequency (and energy) in the electromagnetic spectrum','diff'=>'Hard'],
            ['q'=>'What is a catalyst?','a'=>'A substance that speeds up a reaction without being consumed','b'=>'A reactant that is used up','c'=>'A product of the reaction','d'=>'A substance that slows down a reaction','ans'=>'A','exp'=>'A catalyst increases reaction rate without being permanently consumed','diff'=>'Hard'],
        ];
    }

    // ── GENERAL KNOWLEDGE ─────────────────────────────────────────────────────

    private function generalQuestions(string $level): array
    {
        $all = [
            ['q'=>'Which is the largest continent?','a'=>'Africa','b'=>'Asia','c'=>'Europe','d'=>'America','ans'=>'B','exp'=>'Asia is the largest continent by both area and population','diff'=>'Easy'],
            ['q'=>'How many days are in a leap year?','a'=>'364','b'=>'365','c'=>'366','d'=>'367','ans'=>'C','exp'=>'A leap year has 366 days (February has 29 days)','diff'=>'Easy'],
            ['q'=>'What is the capital of India?','a'=>'Mumbai','b'=>'Kolkata','c'=>'New Delhi','d'=>'Chennai','ans'=>'C','exp'=>'New Delhi is the capital of India','diff'=>'Easy'],
            ['q'=>'How many sides does a hexagon have?','a'=>'5','b'=>'6','c'=>'7','d'=>'8','ans'=>'B','exp'=>'A hexagon has 6 sides','diff'=>'Easy'],
            ['q'=>'Which is the longest river in the world?','a'=>'Amazon','b'=>'Nile','c'=>'Yangtze','d'=>'Ganges','ans'=>'B','exp'=>'The Nile river in Africa is generally considered the longest','diff'=>'Easy'],
            ['q'=>'What is the national animal of India?','a'=>'Lion','b'=>'Elephant','c'=>'Tiger','d'=>'Peacock','ans'=>'C','exp'=>'The Bengal Tiger is India\'s national animal','diff'=>'Easy'],
            ['q'=>'How many planets are in our solar system?','a'=>'7','b'=>'8','c'=>'9','d'=>'10','ans'=>'B','exp'=>'There are 8 planets (Pluto was reclassified as a dwarf planet in 2006)','diff'=>'Easy'],
            ['q'=>'What is the full form of CPU?','a'=>'Central Processing Unit','b'=>'Computer Processing Unit','c'=>'Central Program Unit','d'=>'Core Processing Unit','ans'=>'A','exp'=>'CPU = Central Processing Unit — the brain of a computer','diff'=>'Easy'],
            ['q'=>'Who invented the telephone?','a'=>'Thomas Edison','b'=>'Nikola Tesla','c'=>'Alexander Graham Bell','d'=>'Guglielmo Marconi','ans'=>'C','exp'=>'Alexander Graham Bell invented the telephone in 1876','diff'=>'Easy'],
            ['q'=>'Which is the largest ocean?','a'=>'Atlantic','b'=>'Indian','c'=>'Arctic','d'=>'Pacific','ans'=>'D','exp'=>'The Pacific Ocean is the largest ocean, covering about 165 million km²','diff'=>'Easy'],
            ['q'=>'What does WWW stand for?','a'=>'World Wide Web','b'=>'World Web Wide','c'=>'Wide World Web','d'=>'Web World Wide','ans'=>'A','exp'=>'WWW = World Wide Web — a system of linked documents on the internet','diff'=>'Medium'],
            ['q'=>'Who wrote the Indian national anthem?','a'=>'Bankim Chandra Chatterjee','b'=>'Rabindranath Tagore','c'=>'Sarojini Naidu','d'=>'Mahatma Gandhi','ans'=>'B','exp'=>'Rabindranath Tagore wrote "Jana Gana Mana" — India\'s national anthem','diff'=>'Medium'],
            ['q'=>'What is the capital of Tamil Nadu?','a'=>'Madurai','b'=>'Coimbatore','c'=>'Chennai','d'=>'Trichy','ans'=>'C','exp'=>'Chennai is the capital of Tamil Nadu','diff'=>'Medium'],
            ['q'=>'What is the currency of Japan?','a'=>'Won','b'=>'Yuan','c'=>'Yen','d'=>'Rupee','ans'=>'C','exp'=>'Japan\'s currency is the Yen (¥)','diff'=>'Medium'],
            ['q'=>'How many states are in India (as of 2024)?','a'=>'28','b'=>'29','c'=>'30','d'=>'27','ans'=>'A','exp'=>'India has 28 states and 8 Union Territories as of 2024','diff'=>'Medium'],
            ['q'=>'Who is the author of "Wings of Fire"?','a'=>'Jawaharlal Nehru','b'=>'A.P.J. Abdul Kalam','c'=>'Narendra Modi','d'=>'S. Radhakrishnan','ans'=>'B','exp'=>'"Wings of Fire" is the autobiography of Dr. A.P.J. Abdul Kalam','diff'=>'Medium'],
            ['q'=>'What is the boiling point of water in Fahrenheit?','a'=>'200°F','b'=>'210°F','c'=>'212°F','d'=>'220°F','ans'=>'C','exp'=>'Water boils at 212°F (= 100°C)','diff'=>'Medium'],
            ['q'=>'Which is the highest mountain in the world?','a'=>'K2','b'=>'Kangchenjunga','c'=>'Mont Blanc','d'=>'Mount Everest','ans'=>'D','exp'=>'Mount Everest (8,848 m) is the highest mountain in the world','diff'=>'Medium'],
            ['q'=>'What does LED stand for?','a'=>'Light Emitting Diode','b'=>'Light Energy Device','c'=>'Low Emission Diode','d'=>'Laser Emitting Diode','ans'=>'A','exp'=>'LED = Light Emitting Diode — a semiconductor light source','diff'=>'Medium'],
            ['q'=>'What year did India gain independence?','a'=>'1945','b'=>'1946','c'=>'1947','d'=>'1948','ans'=>'C','exp'=>'India gained independence from British rule on August 15, 1947','diff'=>'Medium'],
            ['q'=>'What is the study of fossils called?','a'=>'Geology','b'=>'Archaeology','c'=>'Palaeontology','d'=>'Anthropology','ans'=>'C','exp'=>'Palaeontology is the scientific study of fossils','diff'=>'Hard'],
            ['q'=>'What is the Doppler Effect?','a'=>'Change in wave frequency due to relative motion','b'=>'Reflection of sound waves','c'=>'Absorption of light by matter','d'=>'Refraction of light','ans'=>'A','exp'=>'Doppler Effect: frequency of a wave changes as source or observer moves','diff'=>'Hard'],
            ['q'=>'What is the term for when a government controls all means of production?','a'=>'Capitalism','b'=>'Democracy','c'=>'Communism','d'=>'Federalism','ans'=>'C','exp'=>'Communism advocates state/collective ownership of all means of production','diff'=>'Hard'],
            ['q'=>'Who proposed the theory of general relativity?','a'=>'Isaac Newton','b'=>'Niels Bohr','c'=>'Albert Einstein','d'=>'Max Planck','ans'=>'C','exp'=>'Albert Einstein proposed the theory of general relativity in 1915','diff'=>'Hard'],
            ['q'=>'What is the GDP of a country?','a'=>'Government Debt Product','b'=>'Gross Domestic Product','c'=>'General Development Plan','d'=>'Global Distribution Policy','ans'=>'B','exp'=>'GDP = Gross Domestic Product — total value of goods/services produced in a country','diff'=>'Hard'],
        ];

        $easy   = array_slice($all, 0, 10);
        $medium = array_slice($all, 10, 10);
        $hard   = array_slice($all, 20, 5);
        return $this->build100($easy, $medium, $hard, $level);
    }

    // ── BUILD 100 QUESTIONS ───────────────────────────────────────────────────

    /**
     * Take 3 pools (easy/medium/hard) and return exactly 100 questions.
     * Distribution changes by class level:
     *   nursery/primary_low : 70 easy + 25 medium + 5 hard
     *   primary_high        : 50 easy + 35 medium + 15 hard
     *   middle              : 25 easy + 45 medium + 30 hard
     *   secondary           : 15 easy + 35 medium + 50 hard
     */
    private function build100(array $easy, array $medium, array $hard, string $level): array
    {
        $dist = match($level) {
            'nursery','primary_low' => [70, 25, 5],
            'primary_high'          => [50, 35, 15],
            'middle'                => [25, 45, 30],
            default                 => [15, 35, 50],
        };

        $result = [];
        $result = array_merge($result, $this->fillPool($easy,   $dist[0]));
        $result = array_merge($result, $this->fillPool($medium, $dist[1]));
        $result = array_merge($result, $this->fillPool($hard,   $dist[2]));

        shuffle($result);
        return $result;
    }

    /**
     * Repeat/cycle from a pool to fill exactly $count questions.
     */
    private function fillPool(array $pool, int $count): array
    {
        if (empty($pool) || $count === 0) return [];
        $result = [];
        $i = 0;
        while (count($result) < $count) {
            $q = $pool[$i % count($pool)];
            // Vary slightly if cycling (add index suffix to avoid exact duplicates)
            if ($i >= count($pool)) {
                $q['q'] = $q['q'] . ' [' . (intdiv($i, count($pool)) + 1) . ']';
            }
            $result[] = $q;
            $i++;
        }
        return array_slice($result, 0, $count);
    }
}
