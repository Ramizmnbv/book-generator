<?php

namespace App\Service;

use Faker\Factory as FakerFactory;
use Faker\Generator as FakerGenerator;
use Random\Engine\Mt19937;
use Random\Randomizer;

class BookGeneratorService
{
    // Supported locales for Faker
    private const LOCALES = [
        'en_US' => 'English (USA)',
        'de_DE' => 'German (Germany)',
        'ja_JP' => 'Japanese (Japan)',
        // Add more locales as needed
    ];

    public function getAvailableLocales(): array
    {
        return self::LOCALES;
    }

    /**
     * Generates a list of fake books.
     *
     * @param string $locale The locale for data generation (e.g., 'en_US').
     * @param int $userSeed The seed provided by the user.
     * @param float $avgLikes The average number of likes per book.
     * @param float $avgReviews The average number of reviews per book.
     * @param int $startIndex The starting index for the books to generate (0-based).
     * @param int $count The number of books to generate.
     * @return array An array of generated book data.
     */
    public function generateBooks(string $locale, int $userSeed, float $avgLikes, float $avgReviews, int $startIndex, int $count): array
    {
        $books = [];
        $faker = FakerFactory::create($locale);

        for ($i = 0; $i < $count; $i++) {
            $currentBookAbsoluteIndex = $startIndex + $i; // 0-indexed overall

            // Seed for book properties (ISBN, title, author, publisher)
            // Ensures book properties are stable for a given userSeed and book index
            $bookPropertiesSeed = $this->deriveSeedFromString((string)($userSeed + $currentBookAbsoluteIndex) . "_bookprops");
            $faker->seed($bookPropertiesSeed);

            $isbn = $faker->isbn13();
            $title = $this->generateRealisticTitle($faker, $locale);
            
            // Generate 1 to 2 authors
            $numAuthors = $faker->numberBetween(1, 2);
            $authors = [];
            for($j=0; $j < $numAuthors; $j++) {
                // Re-seed for each author to ensure diversity if numAuthors > 1, but linked to book seed
                $faker->seed($bookPropertiesSeed + $j + 1000); // Offset seed for authors
                $authors[] = $faker->name();
            }
            
            $faker->seed($bookPropertiesSeed + 2000); // Offset seed for publisher
            $publisher = $faker->company();

            // Seed for likes count, derived from book's unique ID (ISBN) and user seed
            $likesRngSeed = $this->deriveSeedFromString($isbn . $userSeed . "likes");
            $likesRng = new Randomizer(new Mt19937($likesRngSeed));
            $numLikes = $this->calculateCountWithProbability($avgLikes, $likesRng);

            // Seed for reviews count and content
            $reviewsBaseSeed = $this->deriveSeedFromString($isbn . $userSeed . "reviews");
            $reviewsCountRng = new Randomizer(new Mt19937($reviewsBaseSeed));
            $numReviews = $this->calculateCountWithProbability($avgReviews, $reviewsCountRng);

            $bookReviews = [];
            if ($numReviews > 0) {
                $fakerForReviews = FakerFactory::create($locale); // Separate Faker for reviews to avoid state conflicts
                for ($j = 0; $j < $numReviews; $j++) {
                    // Seed for each individual review to ensure its stability and uniqueness
                    $individualReviewSeed = $this->deriveSeedFromString($reviewsBaseSeed . "_review_" . $j);
                    $fakerForReviews->seed($individualReviewSeed);
                    $bookReviews[] = [
                        'text' => $this->generateRealisticReviewText($fakerForReviews, $locale),
                        'author' => $fakerForReviews->name(),
                    ];
                }
            }
            
            $coverImageUrl = $this->generateCoverImageUrl($title, implode(', ', $authors));

            $books[] = [
                'index' => $currentBookAbsoluteIndex + 1, // 1-based for display
                'isbn' => $isbn,
                'title' => $title,
                'authors' => $authors,
                'publisher' => $publisher,
                'likes' => $numLikes,
                'reviews' => $bookReviews,
                'coverImageUrl' => $coverImageUrl,
            ];
        }

        return $books;
    }

    /**
     * Generates a somewhat realistic title based on locale.
     */
    private function generateRealisticTitle(FakerGenerator $faker, string $locale): string
    {
        // For Japanese, try to get a few words. For others, a sentence.
        // Faker's realText might sometimes be too long for a title.
        // You might want to customize this with more sophisticated title generation logic
        // or shorter text pieces.
        try {
            if ($locale === 'ja_JP') {
                return $faker->realText(max(10, random_int(15,30))); // Shorter for JP
            }
            return $faker->catchPhrase() . ($faker->boolean(25) ? ': ' . $faker->bs() : ''); // Catchy title
        } catch (\Exception $e) {
            // Fallback if realText or other methods fail for a locale
            return $faker->sentence(random_int(3,7));
        }
    }
    
    /**
     * Generates somewhat realistic review text.
     */
    private function generateRealisticReviewText(FakerGenerator $faker, string $locale): string
    {
         try {
            // Using realText to get more "natural" looking text of varying length
            return $faker->realText(random_int(50, 200));
        } catch (\Exception $e) {
            // Fallback for locales where realText might not be well-supported or empty
            return $faker->paragraph(random_int(1,3));
        }
    }


    /**
     * Derives an integer seed from a string.
     */
    private function deriveSeedFromString(string $stringInput): int
    {
        // crc32 returns a signed int on 32-bit, unsigned on 64-bit.
        // We want a consistent positive integer for seeding.
        $hash = crc32($stringInput);
        return abs($hash);
    }

    /**
     * Calculates the number of items (e.g., likes, reviews) to generate based on an average.
     * The fractional part of the average determines the probability of adding one more item.
     *
     * @param float $average The average number of items.
     * @param Randomizer $rng A seeded Randomizer instance.
     * @return int The actual number of items to generate.
     */
    private function calculateCountWithProbability(float $average, Randomizer $rng): int
    {
        if ($average < 0) {
            return 0;
        }
        $wholePart = floor($average);
        $fractionalPart = $average - $wholePart;
        
        $additionalCount = 0;
        // Check fractional part with a small epsilon for float comparisons
        if ($fractionalPart > 1e-9 && $rng->nextFloat() < $fractionalPart) {
            $additionalCount = 1;
        }
        return (int) ($wholePart + $additionalCount);
    }

    /**
     * Generates a placeholder cover image URL.
     */
    private function generateCoverImageUrl(string $title, string $author): string
    {
        $width = 200;
        $height = 300;
        $bgColor = substr(md5($title), 0, 6); // Pseudo-random color based on title
        $textColor = '333333';
        
        // Shorten title and author for display on image if too long
        $displayText = substr($title, 0, 20) . (strlen($title) > 20 ? "..." : "");
        $displayAuthor = substr($author, 0, 20) . (strlen($author) > 20 ? "..." : "");

        $textOnCover = rawurlencode($displayText . "\n" . $displayAuthor);
        return "https://placehold.co/{$width}x{$height}/{$bgColor}/{$textColor}?text={$textOnCover}&font=lora";
    }
}
