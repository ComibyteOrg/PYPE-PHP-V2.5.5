<?php

namespace Framework\Seeder;

/**
 * Database Seeder & Faker
 * Seed database with generated test data.
 *
 * Usage:
 * class UserSeeder extends Seeder
 * {
 *     public function run(): void
 *     {
 *         for ($i = 0; $i < 10; $i++) {
 *             User::create([
 *                 'name' => $this->faker->name(),
 *                 'email' => $this->faker->uniqueEmail(),
 *                 'password' => hash_password('password'),
 *             ]);
 *         }
 *     }
 * }
 *
 * (new UserSeeder())->run();
 * Seeder::runAll();
 */
abstract class Seeder
{
    protected Faker $faker;

    public function __construct()
    {
        $this->faker = new Faker();
    }

    abstract public function run(): void;

    protected function call(string $seeder): void
    {
        $instance = new $seeder();
        $instance->run();
    }

    protected function truncate(string $table): void
    {
        $db = \Framework\Database\DatabaseQuery::pdo();
        $db->exec("TRUNCATE TABLE {$table}");
    }
}

class SeederManager
{
    protected static array $seeders = [];

    public static function add(string $seeder): void
    {
        self::$seeders[] = $seeder;
    }

    public static function runAll(): void
    {
        foreach (self::$seeders as $seeder) {
            echo "Seeding: {$seeder}...\n";
            $instance = new $seeder();
            $instance->run();
        }
        echo "All seeders completed.\n";
    }

    public static function runOne(string $seeder): void
    {
        if (class_exists($seeder)) {
            echo "Seeding: {$seeder}...\n";
            $instance = new $seeder();
            $instance->run();
            echo "Done.\n";
        } else {
            echo "Seeder class not found: {$seeder}\n";
        }
    }
}

class Faker
{
    protected array $firstNames = ['James', 'Mary', 'John', 'Patricia', 'Robert', 'Jennifer', 'Michael', 'Linda', 'William', 'Elizabeth', 'David', 'Barbara', 'Richard', 'Susan', 'Joseph', 'Jessica', 'Thomas', 'Sarah', 'Charles', 'Karen'];
    protected array $lastNames = ['Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Garcia', 'Miller', 'Davis', 'Rodriguez', 'Martinez', 'Hernandez', 'Lopez', 'Gonzalez', 'Wilson', 'Anderson', 'Thomas', 'Taylor', 'Moore', 'Jackson', 'Martin'];
    protected array $domains = ['gmail.com', 'yahoo.com', 'outlook.com', 'example.com', 'test.com'];
    protected array $tlds = ['com', 'org', 'net', 'io', 'dev'];
    protected array $companies = ['Acme Corp', 'Globex', 'Initech', 'Umbrella', 'Stark Industries', 'Wayne Enterprises', 'Cyberdyne', 'Oscorp', 'LexCorp', 'Soylent Corp'];
    protected array $cities = ['New York', 'Los Angeles', 'Chicago', 'Houston', 'Phoenix', 'Philadelphia', 'San Antonio', 'San Diego', 'Dallas', 'Austin'];
    protected array $streets = ['Main St', 'Oak Ave', 'Maple Dr', 'Cedar Ln', 'Pine Rd', 'Elm St', 'Park Ave', 'Lake Dr', 'Hill Rd', 'River Rd'];

    public function name(): string
    {
        return $this->firstName() . ' ' . $this->lastName();
    }

    public function firstName(): string
    {
        return $this->randomElement($this->firstNames);
    }

    public function lastName(): string
    {
        return $this->randomElement($this->lastNames);
    }

    public function email(): string
    {
        $name = strtolower($this->firstName() . '.' . $this->lastName());
        $domain = $this->randomElement($this->domains);
        return $name . $this->numberBetween(1, 999) . '@' . $domain;
    }

    public function uniqueEmail(): string
    {
        static $index = 0;
        $index++;
        return 'user' . $index . '@example.com';
    }

    public function username(): string
    {
        return strtolower($this->firstName() . '_' . $this->numberBetween(1, 999));
    }

    public function phoneNumber(): string
    {
        return '(' . $this->numberBetween(200, 999) . ') ' . $this->numberBetween(200, 999) . '-' . $this->numberBetween(1000, 9999);
    }

    public function address(): string
    {
        return $this->numberBetween(100, 9999) . ' ' . $this->randomElement($this->streets);
    }

    public function city(): string
    {
        return $this->randomElement($this->cities);
    }

    public function state(): string
    {
        $states = ['CA', 'NY', 'TX', 'FL', 'IL', 'PA', 'OH', 'GA', 'NC', 'MI'];
        return $this->randomElement($states);
    }

    public function country(): string
    {
        $countries = ['US', 'CA', 'UK', 'DE', 'FR', 'AU', 'JP', 'BR', 'IN', 'NG'];
        return $this->randomElement($countries);
    }

    public function companyName(): string
    {
        return $this->randomElement($this->companies);
    }

    public function jobTitle(): string
    {
        $titles = ['Developer', 'Designer', 'Manager', 'Engineer', 'Analyst', 'Director', 'VP', 'CEO', 'CTO', 'Architect'];
        return $this->randomElement($titles);
    }

    public function domain(): string
    {
        return strtolower($this->lastName()) . '.' . $this->randomElement($this->tlds);
    }

    public function url(): string
    {
        return 'https://' . $this->domain();
    }

    public function title(): string
    {
        $words = ['The', 'A', 'An'];
        $adjectives = ['Amazing', 'Beautiful', 'Creative', 'Dynamic', 'Essential', 'Fantastic', 'Great', 'Important', 'Modern', 'New'];
        $nouns = ['Guide', 'Tutorial', 'Introduction', 'Overview', 'Review', 'Analysis', 'Tips', 'Strategies', 'Methods', 'Approach'];

        return $this->randomElement($words) . ' ' . $this->randomElement($adjectives) . ' ' . $this->randomElement($nouns);
    }

    public function text(int $sentences = 3): string
    {
        $sentences = [
            'The quick brown fox jumps over the lazy dog.',
            'Lorem ipsum dolor sit amet, consectetur adipiscing elit.',
            'Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.',
            'Ut enim ad minim veniam, quis nostrud exercitation ullamco.',
            'Duis aute irure dolor in reprehenderit in voluptate velit.',
            'Excepteur sint occaecat cupidatat non proident.',
            'Sunt in culpa qui officia deserunt mollit anim id est laborum.',
            'Curabitur pretium tincidunt lacus nulla gravida orci.',
            'Nullam ac tortor vitae purus faucibus ornare suspendisse.',
            'Vestibulum ante ipsum primis in faucibus orci luctus.',
        ];

        $result = [];
        for ($i = 0; $i < $sentences; $i++) {
            $result[] = $this->randomElement($sentences);
        }
        return implode(' ', $result);
    }

    public function paragraph(): string
    {
        return $this->text(5);
    }

    public function sentence(): string
    {
        return $this->text(1);
    }

    public function word(): string
    {
        $words = ['lorem', 'ipsum', 'dolor', 'sit', 'amet', 'consectetur', 'adipiscing', 'elit', 'sed', 'do', 'eiusmod', 'tempor', 'incididunt', 'labore', 'dolore', 'magna', 'aliqua'];
        return $this->randomElement($words);
    }

    public function words(int $count = 5): string
    {
        $words = [];
        for ($i = 0; $i < $count; $i++) {
            $words[] = $this->word();
        }
        return implode(' ', $words);
    }

    public function numberBetween(int $min = 0, int $max = 9999): int
    {
        return random_int($min, $max);
    }

    public function randomNumber(int $digits = 5): int
    {
        return (int) str_pad((string) random_int(0, (int) str_repeat('9', $digits)), $digits, '0', STR_PAD_LEFT);
    }

    public function randomFloat(int $decimals = 2, float $min = 0, float $max = 100): float
    {
        return round($min + mt_rand() / mt_getrandmax() * ($max - $min), $decimals);
    }

    public function randomElement(array $array): mixed
    {
        return $array[array_rand($array)];
    }

    public function randomKey(array $array): mixed
    {
        $keys = array_keys($array);
        return $keys[array_rand($keys)];
    }

    public function shuffle(array $array): array
    {
        shuffle($array);
        return $array;
    }

    public function boolean(): bool
    {
        return (bool) random_int(0, 1);
    }

    public function dateTime(?string $format = 'Y-m-d H:i:s', ?string $max = null): string
    {
        $max = $max ?? 'now';
        $maxTime = is_numeric($max) ? (int) $max : strtotime($max);
        $randomTime = random_int(strtotime('2020-01-01'), $maxTime);
        return date($format, $randomTime);
    }

    public function date(string $format = 'Y-m-d', ?string $max = null): string
    {
        return $this->dateTime($format, $max);
    }

    public function time(string $format = 'H:i:s'): string
    {
        return $this->dateTime($format);
    }

    public function color(): string
    {
        return '#' . substr(str_shuffle('0123456789ABCDEF'), 0, 6);
    }

    public function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    public function imageUrl(int $width = 640, int $height = 480, ?string $category = null): string
    {
        $url = "https://picsum.photos/{$width}/{$height}";
        return $url;
    }
}
