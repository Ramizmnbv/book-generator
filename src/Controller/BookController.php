<?php

namespace App\Controller;

use App\Service\BookGeneratorService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class BookController extends AbstractController
{
    private BookGeneratorService $generatorService;

    public function __construct(BookGeneratorService $generatorService)
    {
        $this->generatorService = $generatorService;
    }

    #[Route('/', name: 'app_home')]
    public function index(): Response
    {
        // Provide available locales to the template
        return $this->render('book/index.html.twig', [
            'available_locales' => $this->generatorService->getAvailableLocales(),
        ]);
    }

    #[Route('/api/books', name: 'api_get_books', methods: ['GET'])]
    public function getBooks(Request $request): JsonResponse
    {
        // Validate and sanitize inputs
        $locale = $request->query->get('locale', 'en_US');
        if (!array_key_exists($locale, $this->generatorService->getAvailableLocales())) {
            return $this->json(['error' => 'Invalid locale specified.'], Response::HTTP_BAD_REQUEST);
        }

        // Seed: try to get from query, default to a random one if not provided or invalid
        $seedInput = $request->query->get('seed');
        if ($seedInput === null || !is_numeric($seedInput) || (int)$seedInput <= 0) {
            try {
                $seed = random_int(1, 10000000);
            } catch (\Exception $e) {
                $seed = 12345; // Fallback
            }
        } else {
            $seed = (int)$seedInput;
        }
        
        $avgLikes = filter_var($request->query->get('avgLikes', '1.0'), FILTER_VALIDATE_FLOAT);
        if ($avgLikes === false || $avgLikes < 0 || $avgLikes > 10) {
            $avgLikes = 1.0; // Default or error
        }

        $avgReviews = filter_var($request->query->get('avgReviews', '1.0'), FILTER_VALIDATE_FLOAT);
         if ($avgReviews === false || $avgReviews < 0) { // No upper limit specified, but let's be reasonable
            $avgReviews = 1.0; // Default or error
        }


        $startIndex = filter_var($request->query->get('startIndex', '0'), FILTER_VALIDATE_INT);
        if ($startIndex === false || $startIndex < 0) {
            $startIndex = 0;
        }
        
        $count = filter_var($request->query->get('count', '20'), FILTER_VALIDATE_INT);
        // Initial load 20, subsequent 10
        if ($count === false || !in_array($count, [10, 20]) || $count <=0) {
            $count = ($startIndex === 0) ? 20 : 10;
        }


        try {
            $booksData = $this->generatorService->generateBooks(
                $locale,
                $seed,
                $avgLikes,
                $avgReviews,
                $startIndex,
                $count
            );
            return $this->json([
                'books' => $booksData,
                'nextSeed' => $seed, // Return the used seed, especially if it was auto-generated
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (\Exception $e) {
            // Log the error: $this->container->get('logger')->error($e->getMessage());
            return $this->json(['error' => 'An unexpected error occurred during book generation. ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
