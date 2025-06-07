<?php

namespace App\Controller;

use App\Service\BookGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class BookController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(): Response
    {
        return $this->render('book/index.html.twig');
    }

    #[Route('/api/books', name: 'api_books')]
    public function getBooks(Request $request, BookGenerator $bookGenerator): Response
    {
        $locale = $request->query->get('locale', 'en_US');
        $seed = $request->query->getInt('seed', random_int(1, 1000000));
        $likes = $request->query->getfloat('likes', 1.0);
        $reviews = $request->query->getfloat('reviews', 1.0);
        
        // For infinite scroll, page 1 has 20 items, subsequent pages have 10
        $page = $request->query->getInt('page', 1);
        $perPage = ($page == 1) ? 20 : 10;

        $books = $bookGenerator->generateBooks($locale, $seed, $likes, $reviews, $page, $perPage);

        // Check if the request is for a turbo-stream (for infinite scrolling)
        if ($request->headers->get('Accept') === 'text/vnd.turbo-stream.html' && $page > 1) {
            // Append new content
            return $this->render('book/_book_stream.stream.html.twig', [
                'books' => $books,
            ]);
        }

        // Render a Twig partial. This is great for Turbo Streams.
        return $this->render('book/_book_list.html.twig', [
            'books' => $books,
        ]);
    }
}