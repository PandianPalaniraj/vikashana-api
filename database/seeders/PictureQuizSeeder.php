<?php

namespace Database\Seeders;

use App\Models\School;
use App\Models\SchoolClass;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PictureQuizSeeder extends Seeder
{
    // Wikimedia Commons base for thumbnail images
    private const WM = 'https://upload.wikimedia.org/wikipedia/commons/thumb';

    public function run(): void
    {
        $schools = School::all();
        if ($schools->isEmpty()) {
            $this->command->warn('No schools found. Skipping.');
            return;
        }

        $totalInserted = 0;

        foreach ($schools as $school) {
            $earlyClasses = SchoolClass::where('school_id', $school->id)
                ->whereIn('name', ['Nursery', 'LKG', 'UKG'])
                ->get();

            if ($earlyClasses->isEmpty()) {
                $this->command->warn("  No Nursery/LKG/UKG classes for school: {$school->name}");
                continue;
            }

            foreach ($earlyClasses as $class) {
                // Create or find "Picture Quiz" subject for this class
                $subject = DB::table('subjects')
                    ->where('school_id', $school->id)
                    ->where('class_id', $class->id)
                    ->where('name', 'Picture Quiz')
                    ->first();

                if (!$subject) {
                    $subjectId = DB::table('subjects')->insertGetId([
                        'school_id'   => $school->id,
                        'class_id'    => $class->id,
                        'name'        => 'Picture Quiz',
                        'code'        => 'PQ',
                        'is_optional' => 0,
                        'created_at'  => now(),
                        'updated_at'  => now(),
                    ]);
                } else {
                    $subjectId = $subject->id;
                }

                // Skip if already seeded
                $existing = DB::table('quiz_questions')
                    ->where('school_id', $school->id)
                    ->where('class_id', $class->id)
                    ->where('subject_id', $subjectId)
                    ->whereNotNull('image_url')
                    ->count();

                if ($existing > 0) {
                    $this->command->line("  Skipping {$class->name} / Picture Quiz (already seeded: {$existing} questions)");
                    continue;
                }

                $questions = $this->getPictureQuestions();
                $inserted  = 0;

                foreach ($questions as $q) {
                    DB::table('quiz_questions')->insert([
                        'school_id'      => $school->id,
                        'class_id'       => $class->id,
                        'subject_id'     => $subjectId,
                        'question'       => $q['q'],
                        'image_url'      => $q['img'],
                        'option_a'       => $q['a'],
                        'option_b'       => $q['b'],
                        'option_c'       => $q['c'],
                        'option_d'       => $q['d'],
                        'correct_answer' => $q['ans'],
                        'explanation'    => $q['exp'] ?? null,
                        'difficulty'     => 'Easy',
                        'status'         => 'Active',
                        'created_at'     => now(),
                        'updated_at'     => now(),
                    ]);
                    $inserted++;
                }

                $totalInserted += $inserted;
                $this->command->info("  ✓ {$class->name} / Picture Quiz — {$inserted} questions");
            }
        }

        $this->command->info("Picture quiz seeder complete — {$totalInserted} questions inserted.");
    }

    // ── All picture questions ────────────────────────────────────────────────

    private function getPictureQuestions(): array
    {
        return array_merge(
            $this->animals(),
            $this->fruits(),
            $this->vegetables(),
            $this->vehicles(),
            $this->colors(),
            $this->shapes(),
            $this->bodyParts(),
            $this->schoolItems()
        );
    }

    // ── Animals ──────────────────────────────────────────────────────────────

    private function animals(): array
    {
        $wm = self::WM;
        return [
            [
                'q'   => 'What animal is this? 🐱',
                'img' => "{$wm}/b/bb/Kittyply_edit1.jpg/300px-Kittyply_edit1.jpg",
                'a'   => 'Cat', 'b' => 'Dog', 'c' => 'Rabbit', 'd' => 'Fox',
                'ans' => 'A', 'exp' => 'This is a Cat. Cats say "meow".',
            ],
            [
                'q'   => 'What animal is this? 🐶',
                'img' => "{$wm}/2/26/YellowLabradorLooking_new.jpg/300px-YellowLabradorLooking_new.jpg",
                'a'   => 'Wolf', 'b' => 'Dog', 'c' => 'Fox', 'd' => 'Bear',
                'ans' => 'B', 'exp' => 'This is a Dog. Dogs say "woof".',
            ],
            [
                'q'   => 'What animal is this? 🐘',
                'img' => "{$wm}/3/37/African_Bush_Elephant.jpg/300px-African_Bush_Elephant.jpg",
                'a'   => 'Hippo', 'b' => 'Rhino', 'c' => 'Elephant', 'd' => 'Mammoth',
                'ans' => 'C', 'exp' => 'This is an Elephant. It has a long trunk.',
            ],
            [
                'q'   => 'What animal is this? 🦁',
                'img' => "{$wm}/7/73/Lion_waiting_in_Namibia.jpg/300px-Lion_waiting_in_Namibia.jpg",
                'a'   => 'Tiger', 'b' => 'Leopard', 'c' => 'Cheetah', 'd' => 'Lion',
                'ans' => 'D', 'exp' => 'This is a Lion. It is the king of the jungle.',
            ],
            [
                'q'   => 'What animal is this? 🐯',
                'img' => "{$wm}/3/3f/Walking_tiger_female.jpg/300px-Walking_tiger_female.jpg",
                'a'   => 'Tiger', 'b' => 'Lion', 'c' => 'Leopard', 'd' => 'Jaguar',
                'ans' => 'A', 'exp' => 'This is a Tiger. It has black stripes.',
            ],
            [
                'q'   => 'What animal is this? 🐄',
                'img' => "{$wm}/0/0a/Cow_female_black_white.jpg/300px-Cow_female_black_white.jpg",
                'a'   => 'Goat', 'b' => 'Horse', 'c' => 'Donkey', 'd' => 'Cow',
                'ans' => 'D', 'exp' => 'This is a Cow. Cows give us milk.',
            ],
            [
                'q'   => 'What animal is this? 🐴',
                'img' => "{$wm}/d/de/Nokota_Horses_cropped.jpg/300px-Nokota_Horses_cropped.jpg",
                'a'   => 'Donkey', 'b' => 'Zebra', 'c' => 'Horse', 'd' => 'Camel',
                'ans' => 'C', 'exp' => 'This is a Horse. Horses are fast runners.',
            ],
            [
                'q'   => 'What animal is this? 🐰',
                'img' => "{$wm}/1/1f/Oryctolagus_cuniculus_Rcdo.jpg/300px-Oryctolagus_cuniculus_Rcdo.jpg",
                'a'   => 'Rat', 'b' => 'Squirrel', 'c' => 'Cat', 'd' => 'Rabbit',
                'ans' => 'D', 'exp' => 'This is a Rabbit. Rabbits have long ears.',
            ],
            [
                'q'   => 'What animal is this? 🐠',
                'img' => "{$wm}/7/7e/Clown_fish_in_Pacific_Ocean.jpg/300px-Clown_fish_in_Pacific_Ocean.jpg",
                'a'   => 'Crab', 'b' => 'Fish', 'c' => 'Turtle', 'd' => 'Frog',
                'ans' => 'B', 'exp' => 'This is a Clown Fish. Fish live in water.',
            ],
            [
                'q'   => 'What animal is this? 🦋',
                'img' => "{$wm}/a/a9/Lemon_butterfly.jpg/300px-Lemon_butterfly.jpg",
                'a'   => 'Dragonfly', 'b' => 'Moth', 'c' => 'Bee', 'd' => 'Butterfly',
                'ans' => 'D', 'exp' => 'This is a Butterfly. It has colorful wings.',
            ],
            [
                'q'   => 'What animal is this? 🦆',
                'img' => "{$wm}/1/13/Mallard_%28Anas_platyrhynchos%29.jpg/300px-Mallard_%28Anas_platyrhynchos%29.jpg",
                'a'   => 'Goose', 'b' => 'Duck', 'c' => 'Swan', 'd' => 'Hen',
                'ans' => 'B', 'exp' => 'This is a Duck. Ducks love water.',
            ],
            [
                'q'   => 'What animal is this? 🐒',
                'img' => "{$wm}/5/58/Bonnet_macaque_Macaca_radiata_by_N_A_Nazeer.jpg/300px-Bonnet_macaque_Macaca_radiata_by_N_A_Nazeer.jpg",
                'a'   => 'Gorilla', 'b' => 'Bear', 'c' => 'Monkey', 'd' => 'Raccoon',
                'ans' => 'C', 'exp' => 'This is a Monkey. Monkeys love bananas.',
            ],
            [
                'q'   => 'What animal is this? 🦜',
                'img' => "{$wm}/5/55/Alexandrine_parakeet.jpg/300px-Alexandrine_parakeet.jpg",
                'a'   => 'Crow', 'b' => 'Parrot', 'c' => 'Eagle', 'd' => 'Pigeon',
                'ans' => 'B', 'exp' => 'This is a Parrot. Parrots can talk.',
            ],
            [
                'q'   => 'What animal is this? 🐑',
                'img' => "{$wm}/2/27/Ovis_aries_Tasmania.jpg/300px-Ovis_aries_Tasmania.jpg",
                'a'   => 'Goat', 'b' => 'Dog', 'c' => 'Cat', 'd' => 'Sheep',
                'ans' => 'D', 'exp' => 'This is a Sheep. Sheep give us wool.',
            ],
            [
                'q'   => 'What animal is this? 🐓',
                'img' => "{$wm}/2/2a/Gallus_domesticus.jpg/300px-Gallus_domesticus.jpg",
                'a'   => 'Duck', 'b' => 'Pigeon', 'c' => 'Hen', 'd' => 'Peacock',
                'ans' => 'C', 'exp' => 'This is a Hen. Hens give us eggs.',
            ],
            [
                'q'   => 'What animal is this? 🦓',
                'img' => "{$wm}/e/e3/Plains_Zebra_Equus_quagga.jpg/300px-Plains_Zebra_Equus_quagga.jpg",
                'a'   => 'Horse', 'b' => 'Donkey', 'c' => 'Zebra', 'd' => 'Mule',
                'ans' => 'C', 'exp' => 'This is a Zebra. It has black and white stripes.',
            ],
            [
                'q'   => 'What animal is this? 🐦',
                'img' => "{$wm}/4/45/A_small_cup_of_coffee.JPG/300px-A_small_cup_of_coffee.JPG",
                'a'   => 'Eagle', 'b' => 'Sparrow', 'c' => 'Owl', 'd' => 'Crow',
                'ans' => 'B', 'exp' => 'This is a Sparrow. Sparrows are small birds.',
            ],
            [
                'q'   => 'What animal is this? 🐸',
                'img' => "{$wm}/0/09/Anura.jpg/300px-Anura.jpg",
                'a'   => 'Lizard', 'b' => 'Toad', 'c' => 'Frog', 'd' => 'Turtle',
                'ans' => 'C', 'exp' => 'This is a Frog. Frogs jump and say "ribbit".',
            ],
            [
                'q'   => 'What animal is this? 🐢',
                'img' => "{$wm}/f/f4/Turtle_swimming_at_Marsa_Shouna.jpg/300px-Turtle_swimming_at_Marsa_Shouna.jpg",
                'a'   => 'Crocodile', 'b' => 'Turtle', 'c' => 'Snake', 'd' => 'Lizard',
                'ans' => 'B', 'exp' => 'This is a Turtle. Turtles carry their home on their back.',
            ],
            [
                'q'   => 'What animal is this? 🦚',
                'img' => "{$wm}/e/e2/Peacock_double.jpg/300px-Peacock_double.jpg",
                'a'   => 'Parrot', 'b' => 'Pigeon', 'c' => 'Ostrich', 'd' => 'Peacock',
                'ans' => 'D', 'exp' => 'This is a Peacock. It has beautiful colorful feathers.',
            ],
        ];
    }

    // ── Fruits ───────────────────────────────────────────────────────────────

    private function fruits(): array
    {
        $wm = self::WM;
        return [
            [
                'q'   => 'What fruit is this? 🍎',
                'img' => "{$wm}/1/15/Red_Apple.jpg/300px-Red_Apple.jpg",
                'a'   => 'Apple', 'b' => 'Tomato', 'c' => 'Plum', 'd' => 'Cherry',
                'ans' => 'A', 'exp' => 'This is an Apple. Apples are red, yellow, or green.',
            ],
            [
                'q'   => 'What fruit is this? 🍌',
                'img' => "{$wm}/8/8a/Banana-Rangi_Hawke%27s_Bay_NZ.jpg/300px-Banana-Rangi_Hawke%27s_Bay_NZ.jpg",
                'a'   => 'Mango', 'b' => 'Papaya', 'c' => 'Banana', 'd' => 'Pineapple',
                'ans' => 'C', 'exp' => 'This is a Banana. Bananas are yellow and sweet.',
            ],
            [
                'q'   => 'What fruit is this? 🥭',
                'img' => "{$wm}/9/90/Hapus_Mango.jpg/300px-Hapus_Mango.jpg",
                'a'   => 'Papaya', 'b' => 'Mango', 'c' => 'Peach', 'd' => 'Pear',
                'ans' => 'B', 'exp' => 'This is a Mango. Mango is the king of fruits.',
            ],
            [
                'q'   => 'What fruit is this? 🍊',
                'img' => "{$wm}/4/43/Oranges_and_orange_juice.jpg/300px-Oranges_and_orange_juice.jpg",
                'a'   => 'Lemon', 'b' => 'Grapefruit', 'c' => 'Orange', 'd' => 'Tangerine',
                'ans' => 'C', 'exp' => 'This is an Orange. Oranges are juicy and full of vitamin C.',
            ],
            [
                'q'   => 'What fruit is this? 🍓',
                'img' => "{$wm}/2/29/PerfectStrawberry.jpg/300px-PerfectStrawberry.jpg",
                'a'   => 'Cherry', 'b' => 'Raspberry', 'c' => 'Pomegranate', 'd' => 'Strawberry',
                'ans' => 'D', 'exp' => 'This is a Strawberry. Strawberries are red and sweet.',
            ],
            [
                'q'   => 'What fruit is this? 🍇',
                'img' => "{$wm}/5/5e/Ravat_34_uva.jpg/300px-Ravat_34_uva.jpg",
                'a'   => 'Blueberry', 'b' => 'Grapes', 'c' => 'Raisins', 'd' => 'Olives',
                'ans' => 'B', 'exp' => 'These are Grapes. Grapes grow in bunches.',
            ],
            [
                'q'   => 'What fruit is this? 🍉',
                'img' => "{$wm}/a/a4/Single_watermelon.jpg/300px-Single_watermelon.jpg",
                'a'   => 'Pumpkin', 'b' => 'Muskmelon', 'c' => 'Watermelon', 'd' => 'Cucumber',
                'ans' => 'C', 'exp' => 'This is a Watermelon. It is green outside and red inside.',
            ],
            [
                'q'   => 'What fruit is this? 🍍',
                'img' => "{$wm}/c/cb/Pineapple_and_cross_section.jpg/300px-Pineapple_and_cross_section.jpg",
                'a'   => 'Coconut', 'b' => 'Jackfruit', 'c' => 'Pineapple', 'd' => 'Durian',
                'ans' => 'C', 'exp' => 'This is a Pineapple. It is sweet and tangy.',
            ],
            [
                'q'   => 'What fruit is this? 🍋',
                'img' => "{$wm}/b/b4/Lemon_fruit.jpg/300px-Lemon_fruit.jpg",
                'a'   => 'Lime', 'b' => 'Orange', 'c' => 'Gooseberry', 'd' => 'Lemon',
                'ans' => 'D', 'exp' => 'This is a Lemon. Lemons are sour and yellow.',
            ],
            [
                'q'   => 'What fruit is this? 🥥',
                'img' => "{$wm}/f/f2/Coconut_-_Kerala_India.jpg/300px-Coconut_-_Kerala_India.jpg",
                'a'   => 'Walnut', 'b' => 'Coconut', 'c' => 'Jackfruit', 'd' => 'Breadfruit',
                'ans' => 'B', 'exp' => 'This is a Coconut. Coconut water is very refreshing.',
            ],
            [
                'q'   => 'What fruit is this? 🍒',
                'img' => "{$wm}/b/bb/Cherry_Stella444.jpg/300px-Cherry_Stella444.jpg",
                'a'   => 'Grapes', 'b' => 'Strawberry', 'c' => 'Cherry', 'd' => 'Raspberry',
                'ans' => 'C', 'exp' => 'These are Cherries. Cherries are small and round.',
            ],
            [
                'q'   => 'What fruit is this? 🍑',
                'img' => "{$wm}/e/e2/Papaya_cross_section_BNC.jpg/300px-Papaya_cross_section_BNC.jpg",
                'a'   => 'Mango', 'b' => 'Papaya', 'c' => 'Peach', 'd' => 'Apricot',
                'ans' => 'B', 'exp' => 'This is a Papaya. Papaya is orange inside.',
            ],
            [
                'q'   => 'What fruit is this? 🍈',
                'img' => "{$wm}/8/8f/Guava_ID.jpg/300px-Guava_ID.jpg",
                'a'   => 'Guava', 'b' => 'Pear', 'c' => 'Apple', 'd' => 'Lime',
                'ans' => 'A', 'exp' => 'This is a Guava. Guava is sweet and full of seeds.',
            ],
            [
                'q'   => 'What fruit is this? 💛',
                'img' => "{$wm}/9/9e/Pomegranate_fruit_-_whole.jpg/300px-Pomegranate_fruit_-_whole.jpg",
                'a'   => 'Beetroot', 'b' => 'Onion', 'c' => 'Pomegranate', 'd' => 'Plum',
                'ans' => 'C', 'exp' => 'This is a Pomegranate. It has hundreds of ruby-red seeds.',
            ],
            [
                'q'   => 'What fruit is this? 🌴',
                'img' => "{$wm}/4/4e/Jackfruit_Bangladesh.jpg/300px-Jackfruit_Bangladesh.jpg",
                'a'   => 'Durian', 'b' => 'Breadfruit', 'c' => 'Coconut', 'd' => 'Jackfruit',
                'ans' => 'D', 'exp' => 'This is a Jackfruit. Jackfruit is the largest fruit.',
            ],
        ];
    }

    // ── Vegetables ───────────────────────────────────────────────────────────

    private function vegetables(): array
    {
        $wm = self::WM;
        return [
            [
                'q'   => 'What vegetable is this? 🥕',
                'img' => "{$wm}/a/a2/Vegetable-Carrot-Bundle-wStalks.jpg/300px-Vegetable-Carrot-Bundle-wStalks.jpg",
                'a'   => 'Carrot', 'b' => 'Radish', 'c' => 'Beetroot', 'd' => 'Turnip',
                'ans' => 'A', 'exp' => 'This is a Carrot. Carrots are orange and crunchy.',
            ],
            [
                'q'   => 'What vegetable is this? 🍅',
                'img' => "{$wm}/8/89/Tomato_je.jpg/300px-Tomato_je.jpg",
                'a'   => 'Apple', 'b' => 'Cherry', 'c' => 'Tomato', 'd' => 'Beetroot',
                'ans' => 'C', 'exp' => 'This is a Tomato. Tomatoes are red and juicy.',
            ],
            [
                'q'   => 'What vegetable is this? 🧅',
                'img' => "{$wm}/8/84/Onion_on_white_background.jpg/300px-Onion_on_white_background.jpg",
                'a'   => 'Garlic', 'b' => 'Onion', 'c' => 'Potato', 'd' => 'Radish',
                'ans' => 'B', 'exp' => 'This is an Onion. Onions make our eyes water.',
            ],
            [
                'q'   => 'What vegetable is this? 🥔',
                'img' => "{$wm}/a/ab/Potato_and_cross_section.jpg/300px-Potato_and_cross_section.jpg",
                'a'   => 'Sweet Potato', 'b' => 'Yam', 'c' => 'Turnip', 'd' => 'Potato',
                'ans' => 'D', 'exp' => 'This is a Potato. We make chips from potatoes.',
            ],
            [
                'q'   => 'What vegetable is this? 🌽',
                'img' => "{$wm}/5/51/Corn-maize-korn.jpg/300px-Corn-maize-korn.jpg",
                'a'   => 'Bamboo', 'b' => 'Corn', 'c' => 'Sugar Cane', 'd' => 'Wheat',
                'ans' => 'B', 'exp' => 'This is Corn. Corn is yellow and sweet.',
            ],
            [
                'q'   => 'What vegetable is this? 🥦',
                'img' => "{$wm}/0/03/Fresh_broccoli_head_DS.jpg/300px-Fresh_broccoli_head_DS.jpg",
                'a'   => 'Cauliflower', 'b' => 'Cabbage', 'c' => 'Broccoli', 'd' => 'Spinach',
                'ans' => 'C', 'exp' => 'This is Broccoli. Broccoli is green and healthy.',
            ],
            [
                'q'   => 'What vegetable is this? 🥒',
                'img' => "{$wm}/4/49/Cucumbers.jpg/300px-Cucumbers.jpg",
                'a'   => 'Zucchini', 'b' => 'Cucumber', 'c' => 'Bottle Gourd', 'd' => 'Bitter Gourd',
                'ans' => 'B', 'exp' => 'This is a Cucumber. Cucumbers are cool and refreshing.',
            ],
            [
                'q'   => 'What vegetable is this? 🍆',
                'img' => "{$wm}/d/df/Violeteggplant.jpg/300px-Violeteggplant.jpg",
                'a'   => 'Purple Yam', 'b' => 'Beetroot', 'c' => 'Brinjal', 'd' => 'Turnip',
                'ans' => 'C', 'exp' => 'This is a Brinjal (Eggplant). It is purple and shiny.',
            ],
            [
                'q'   => 'What vegetable is this? 🫑',
                'img' => "{$wm}/b/b4/Sweet-p.jpg/300px-Sweet-p.jpg",
                'a'   => 'Chilli', 'b' => 'Capsicum', 'c' => 'Tomato', 'd' => 'Pumpkin',
                'ans' => 'B', 'exp' => 'This is a Capsicum (Bell Pepper). It comes in many colors.',
            ],
            [
                'q'   => 'What vegetable is this? 🎃',
                'img' => "{$wm}/5/5c/Pumpkin%2C_cross_section.jpg/300px-Pumpkin%2C_cross_section.jpg",
                'a'   => 'Watermelon', 'b' => 'Muskmelon', 'c' => 'Papaya', 'd' => 'Pumpkin',
                'ans' => 'D', 'exp' => 'This is a Pumpkin. Pumpkins are big and orange.',
            ],
        ];
    }

    // ── Vehicles ─────────────────────────────────────────────────────────────

    private function vehicles(): array
    {
        $wm = self::WM;
        return [
            [
                'q'   => 'What vehicle is this? 🚗',
                'img' => "{$wm}/1/1b/2016_Hyundai_Sonata_%282%29.jpg/300px-2016_Hyundai_Sonata_%282%29.jpg",
                'a'   => 'Car', 'b' => 'Bus', 'c' => 'Truck', 'd' => 'Van',
                'ans' => 'A', 'exp' => 'This is a Car. Cars run on roads.',
            ],
            [
                'q'   => 'What vehicle is this? 🚌',
                'img' => "{$wm}/8/80/Bondi_Beach_bus.jpg/300px-Bondi_Beach_bus.jpg",
                'a'   => 'Train', 'b' => 'Truck', 'c' => 'Bus', 'd' => 'Tram',
                'ans' => 'C', 'exp' => 'This is a Bus. Buses carry many people.',
            ],
            [
                'q'   => 'What vehicle is this? 🚂',
                'img' => "{$wm}/4/48/Amtrak_Acela_Express.jpg/300px-Amtrak_Acela_Express.jpg",
                'a'   => 'Tram', 'b' => 'Bus', 'c' => 'Subway', 'd' => 'Train',
                'ans' => 'D', 'exp' => 'This is a Train. Trains run on railway tracks.',
            ],
            [
                'q'   => 'What vehicle is this? ✈️',
                'img' => "{$wm}/e/eb/American_Airlines_plane_at_Munich_Airport.jpg/300px-American_Airlines_plane_at_Munich_Airport.jpg",
                'a'   => 'Helicopter', 'b' => 'Airplane', 'c' => 'Rocket', 'd' => 'Hot Air Balloon',
                'ans' => 'B', 'exp' => 'This is an Airplane. Airplanes fly in the sky.',
            ],
            [
                'q'   => 'What vehicle is this? 🚲',
                'img' => "{$wm}/4/41/Left_side_of_a_bicycle.jpg/300px-Left_side_of_a_bicycle.jpg",
                'a'   => 'Motorbike', 'b' => 'Scooter', 'c' => 'Tricycle', 'd' => 'Bicycle',
                'ans' => 'D', 'exp' => 'This is a Bicycle. Bicycles have two wheels.',
            ],
            [
                'q'   => 'What vehicle is this? 🚁',
                'img' => "{$wm}/5/56/Helicopter_DA20.jpg/300px-Helicopter_DA20.jpg",
                'a'   => 'Airplane', 'b' => 'Drone', 'c' => 'Helicopter', 'd' => 'Glider',
                'ans' => 'C', 'exp' => 'This is a Helicopter. Helicopters have spinning blades.',
            ],
            [
                'q'   => 'What vehicle is this? 🚢',
                'img' => "{$wm}/4/46/Queen_Mary_2_at_Hamburg.jpg/300px-Queen_Mary_2_at_Hamburg.jpg",
                'a'   => 'Boat', 'b' => 'Ship', 'c' => 'Submarine', 'd' => 'Ferry',
                'ans' => 'B', 'exp' => 'This is a Ship. Ships sail on the sea.',
            ],
            [
                'q'   => 'What vehicle is this? 🚜',
                'img' => "{$wm}/f/f4/Tractored.jpg/300px-Tractored.jpg",
                'a'   => 'Bulldozer', 'b' => 'Tractor', 'c' => 'Truck', 'd' => 'Crane',
                'ans' => 'B', 'exp' => 'This is a Tractor. Tractors help farmers in fields.',
            ],
            [
                'q'   => 'What vehicle is this? 🚀',
                'img' => "{$wm}/9/9d/Space_Shuttle_Columbia_launching.jpg/300px-Space_Shuttle_Columbia_launching.jpg",
                'a'   => 'Missile', 'b' => 'Airplane', 'c' => 'Rocket', 'd' => 'Satellite',
                'ans' => 'C', 'exp' => 'This is a Rocket. Rockets go into space.',
            ],
            [
                'q'   => 'What vehicle is this? 🏍️',
                'img' => "{$wm}/c/c6/Lifan_LF250.jpg/300px-Lifan_LF250.jpg",
                'a'   => 'Bicycle', 'b' => 'Scooter', 'c' => 'Motorbike', 'd' => 'Tricycle',
                'ans' => 'C', 'exp' => 'This is a Motorbike. Motorbikes are faster than bicycles.',
            ],
        ];
    }

    // ── Colors ───────────────────────────────────────────────────────────────

    private function colors(): array
    {
        $wm = self::WM;
        return [
            [
                'q'   => 'What color is the Apple? 🍎',
                'img' => "{$wm}/1/15/Red_Apple.jpg/300px-Red_Apple.jpg",
                'a'   => 'Blue', 'b' => 'Green', 'c' => 'Red', 'd' => 'Yellow',
                'ans' => 'C', 'exp' => 'The apple is Red. Red is a warm color.',
            ],
            [
                'q'   => 'What color is the Banana? 🍌',
                'img' => "{$wm}/8/8a/Banana-Rangi_Hawke%27s_Bay_NZ.jpg/300px-Banana-Rangi_Hawke%27s_Bay_NZ.jpg",
                'a'   => 'Red', 'b' => 'Blue', 'c' => 'Green', 'd' => 'Yellow',
                'ans' => 'D', 'exp' => 'The banana is Yellow. Yellow is a bright color.',
            ],
            [
                'q'   => 'What color is the sky? ☁️',
                'img' => "{$wm}/4/4c/Wikimedia_Foundation_RGB_logo_with_text.svg/300px-Wikimedia_Foundation_RGB_logo_with_text.svg.png",
                'a'   => 'Blue', 'b' => 'Green', 'c' => 'Red', 'd' => 'Purple',
                'ans' => 'A', 'exp' => 'The sky is Blue during the day.',
            ],
            [
                'q'   => 'What color is grass? 🌿',
                'img' => "{$wm}/5/56/White_shark.jpg/300px-White_shark.jpg",
                'a'   => 'Yellow', 'b' => 'Brown', 'c' => 'Blue', 'd' => 'Green',
                'ans' => 'D', 'exp' => 'Grass is Green.',
            ],
            [
                'q'   => 'What color is the strawberry? 🍓',
                'img' => "{$wm}/2/29/PerfectStrawberry.jpg/300px-PerfectStrawberry.jpg",
                'a'   => 'Pink', 'b' => 'Red', 'c' => 'Orange', 'd' => 'Purple',
                'ans' => 'B', 'exp' => 'The strawberry is Red.',
            ],
            [
                'q'   => 'What color is the orange? 🍊',
                'img' => "{$wm}/4/43/Oranges_and_orange_juice.jpg/300px-Oranges_and_orange_juice.jpg",
                'a'   => 'Yellow', 'b' => 'Red', 'c' => 'Orange', 'd' => 'Brown',
                'ans' => 'C', 'exp' => 'The orange is Orange. Orange is a mix of red and yellow.',
            ],
            [
                'q'   => 'What color is this elephant? 🐘',
                'img' => "{$wm}/3/37/African_Bush_Elephant.jpg/300px-African_Bush_Elephant.jpg",
                'a'   => 'Black', 'b' => 'Brown', 'c' => 'White', 'd' => 'Grey',
                'ans' => 'D', 'exp' => 'The elephant is Grey.',
            ],
        ];
    }

    // ── Shapes ───────────────────────────────────────────────────────────────

    private function shapes(): array
    {
        $wm = self::WM;
        return [
            [
                'q'   => 'What shape is this? ⭕',
                'img' => "{$wm}/b/be/Circle_-_black_simple.svg/300px-Circle_-_black_simple.svg.png",
                'a'   => 'Square', 'b' => 'Triangle', 'c' => 'Circle', 'd' => 'Rectangle',
                'ans' => 'C', 'exp' => 'This is a Circle. A circle is round.',
            ],
            [
                'q'   => 'What shape is this? 🔷',
                'img' => "{$wm}/a/a7/Camponotus_flavomarginatus_ant.jpg/300px-Camponotus_flavomarginatus_ant.jpg",
                'a'   => 'Circle', 'b' => 'Square', 'c' => 'Triangle', 'd' => 'Diamond',
                'ans' => 'D', 'exp' => 'This is a Diamond shape. Diamonds have 4 equal sides.',
            ],
            [
                'q'   => 'What shape does a clock look like? 🕐',
                'img' => "{$wm}/b/be/Circle_-_black_simple.svg/300px-Circle_-_black_simple.svg.png",
                'a'   => 'Square', 'b' => 'Circle', 'c' => 'Triangle', 'd' => 'Rectangle',
                'ans' => 'B', 'exp' => 'A clock is a Circle shape.',
            ],
            [
                'q'   => 'What shape does a pizza slice look like? 🍕',
                'img' => "{$wm}/9/9e/Pizza-3007395.jpg/300px-Pizza-3007395.jpg",
                'a'   => 'Square', 'b' => 'Rectangle', 'c' => 'Circle', 'd' => 'Triangle',
                'ans' => 'D', 'exp' => 'A pizza slice is a Triangle shape.',
            ],
            [
                'q'   => 'What shape does a book look like? 📚',
                'img' => "{$wm}/6/6b/Orange_book.jpg/300px-Orange_book.jpg",
                'a'   => 'Triangle', 'b' => 'Rectangle', 'c' => 'Circle', 'd' => 'Star',
                'ans' => 'B', 'exp' => 'A book is a Rectangle shape.',
            ],
            [
                'q'   => 'What shape does a window look like? 🪟',
                'img' => "{$wm}/d/d1/House_-_icon.svg/300px-House_-_icon.svg.png",
                'a'   => 'Star', 'b' => 'Circle', 'c' => 'Square', 'd' => 'Triangle',
                'ans' => 'C', 'exp' => 'Many windows are Square shaped.',
            ],
            [
                'q'   => 'What shape is a star? ⭐',
                'img' => "{$wm}/4/4f/Twemoji_2b50.svg/300px-Twemoji_2b50.svg.png",
                'a'   => 'Heart', 'b' => 'Star', 'c' => 'Sun', 'd' => 'Flower',
                'ans' => 'B', 'exp' => 'This is a Star shape. Stars shine in the night sky.',
            ],
        ];
    }

    // ── Body Parts ───────────────────────────────────────────────────────────

    private function bodyParts(): array
    {
        $wm = self::WM;
        return [
            [
                'q'   => 'What part of the body is this? 👁️',
                'img' => "{$wm}/a/a7/Iris_-_right_eye_of_a_girl.jpg/300px-Iris_-_right_eye_of_a_girl.jpg",
                'a'   => 'Ear', 'b' => 'Nose', 'c' => 'Eye', 'd' => 'Mouth',
                'ans' => 'C', 'exp' => 'This is an Eye. We use our eyes to see.',
            ],
            [
                'q'   => 'What part of the body is this? 👂',
                'img' => "{$wm}/3/30/Ear_auris.jpg/300px-Ear_auris.jpg",
                'a'   => 'Eye', 'b' => 'Ear', 'c' => 'Nose', 'd' => 'Hand',
                'ans' => 'B', 'exp' => 'This is an Ear. We use our ears to hear.',
            ],
            [
                'q'   => 'What part of the body is this? 👃',
                'img' => "{$wm}/6/61/Human_nose.jpg/300px-Human_nose.jpg",
                'a'   => 'Mouth', 'b' => 'Ear', 'c' => 'Eye', 'd' => 'Nose',
                'ans' => 'D', 'exp' => 'This is a Nose. We use our nose to smell.',
            ],
            [
                'q'   => 'What part of the body is this? 👄',
                'img' => "{$wm}/9/9a/Mouth_smile.jpg/300px-Mouth_smile.jpg",
                'a'   => 'Eye', 'b' => 'Ear', 'c' => 'Mouth', 'd' => 'Nose',
                'ans' => 'C', 'exp' => 'This is a Mouth. We use our mouth to eat and speak.',
            ],
            [
                'q'   => 'What part of the body is this? 🖐️',
                'img' => "{$wm}/2/26/Hand_-_series_02.jpg/300px-Hand_-_series_02.jpg",
                'a'   => 'Foot', 'b' => 'Hand', 'c' => 'Leg', 'd' => 'Arm',
                'ans' => 'B', 'exp' => 'This is a Hand. We have 10 fingers on two hands.',
            ],
            [
                'q'   => 'How many fingers do we have on one hand? ✋',
                'img' => "{$wm}/2/26/Hand_-_series_02.jpg/300px-Hand_-_series_02.jpg",
                'a'   => 'Four', 'b' => 'Six', 'c' => 'Three', 'd' => 'Five',
                'ans' => 'D', 'exp' => 'We have Five fingers on one hand.',
            ],
        ];
    }

    // ── School Items ─────────────────────────────────────────────────────────

    private function schoolItems(): array
    {
        $wm = self::WM;
        return [
            [
                'q'   => 'What is this school item? 📚',
                'img' => "{$wm}/6/6b/Orange_book.jpg/300px-Orange_book.jpg",
                'a'   => 'Notebook', 'b' => 'Book', 'c' => 'Magazine', 'd' => 'Newspaper',
                'ans' => 'B', 'exp' => 'This is a Book. We read books to learn.',
            ],
            [
                'q'   => 'What is this school item? ✏️',
                'img' => "{$wm}/8/8b/Pencil_shaving.jpg/300px-Pencil_shaving.jpg",
                'a'   => 'Crayon', 'b' => 'Pen', 'c' => 'Pencil', 'd' => 'Marker',
                'ans' => 'C', 'exp' => 'This is a Pencil. We write with a pencil.',
            ],
            [
                'q'   => 'What is this? 🎒',
                'img' => "{$wm}/b/b0/School_backpack.jpg/300px-School_backpack.jpg",
                'a'   => 'Suitcase', 'b' => 'Handbag', 'c' => 'Schoolbag', 'd' => 'Basket',
                'ans' => 'C', 'exp' => 'This is a Schoolbag. We carry books in a schoolbag.',
            ],
            [
                'q'   => 'What is this? ⚽',
                'img' => "{$wm}/d/d3/Soccerball.jpg/300px-Soccerball.jpg",
                'a'   => 'Orange', 'b' => 'Ball', 'c' => 'Balloon', 'd' => 'Egg',
                'ans' => 'B', 'exp' => 'This is a Ball. We play with a ball.',
            ],
            [
                'q'   => 'What color is this pencil? ✏️',
                'img' => "{$wm}/8/8b/Pencil_shaving.jpg/300px-Pencil_shaving.jpg",
                'a'   => 'Red', 'b' => 'Blue', 'c' => 'Yellow', 'd' => 'Green',
                'ans' => 'C', 'exp' => 'This pencil is Yellow.',
            ],
        ];
    }
}
