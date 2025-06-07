<?php

namespace App\Service;

use Faker\Factory;
use Faker\Generator;

class BookGenerator
{
    // Function to generate a number of items based on a float value
    // This is the PHP equivalent of the JS times function from the prompt
    private function generateCount(float $average): int
    {
        $count = floor($average);
        if (mt_rand() / mt_getrandmax() < ($average - $count)) {
            $count++;
        }
        return $count;
    }

    public function generateBooks(string $locale, int $seed, float $likes, float $reviews, int $page = 1, int $perPage = 20): array
    {
        $books = [];
        $faker = Factory::create($locale);

        // Crucial: Combine the user seed and page number for reproducible pages
        mt_srand($seed + $page);

        $startIndex = (($page - 1) * $perPage) + 1;

        for ($i = 0; $i < $perPage; $i++) {
            // Main book seed determines its identity (title, author, etc.)
            $bookSeed = mt_rand();
            mt_srand($bookSeed);

            $book = [
                'index' => $startIndex + $i,
                'isbn' => $faker->isbn13(),
                'title' => rtrim($faker->realText(50), '.'),
                'authors' => [],
                'publisher' => $faker->company() . ' ' . $faker->companySuffix(),
                'coverUrl' => '[https://picsum.photos/seed/](https://picsum.photos/seed/)' . $bookSeed . '/400/600',
            ];

            // Generate 1 to 3 authors
            $authorCount = mt_rand(1, 3);
            for ($j = 0; $j < $authorCount; $j++) {
                $book['authors'][] = $faker->name();
            }

            // --- Hierarchical Seeding for Likes & Reviews ---
            // Use a new seed derived from the book's identity. This ensures that
            // changing the 'likes' slider doesn't change the book's title or reviews.
            $detailsSeed = crc32($book['isbn']); // A simple way to get a deterministic seed

            // Generate Likes
            mt_srand($detailsSeed); // Seed for likes
            $book['likes'] = $this->generateCount($likes);

            // Generate Reviews
            mt_srand($detailsSeed + 1); // Seed for reviews (slightly different to avoid collision)
            $reviewCount = $this->generateCount($reviews);
            $book['reviews'] = [];
            for ($k = 0; $k < $reviewCount; $k++) {
                $book['reviews'][] = [
                    'author' => $faker->name(),
                    'text' => $faker->realText(200),
                ];
            }

            $books[] = $book;

            // VERY IMPORTANT: Reseed the main generator to continue the main sequence for the next book
            mt_srand($seed + $page + $i + 1);
        }

        return $books;
    }
}