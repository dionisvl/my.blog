<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class SeoController extends AbstractController
{
    #[Route('/sitemap.xml', name: 'sitemap', methods: ['GET'])]
    public function sitemap(Request $request): Response
    {
        return $this->seoFile($request->getHost(), 'sitemap', 'xml', 'application/xml; charset=utf-8');
    }

    #[Route('/robots.txt', name: 'robots', methods: ['GET'])]
    public function robots(Request $request): Response
    {
        return $this->seoFile($request->getHost(), 'robots', 'txt', 'text/plain; charset=utf-8');
    }

    #[Route('/llms.txt', name: 'llms', methods: ['GET'])]
    public function llms(Request $request): Response
    {
        return $this->seoFile($request->getHost(), 'llms', 'txt', 'text/plain; charset=utf-8');
    }

    private function seoFile(string $host, string $type, string $ext, string $contentType): Response
    {
        $path = $this->getParameter('kernel.project_dir') . '/public/seo/' . $type . '-' . $host . '.' . $ext;

        if (!file_exists($path)) {
            throw $this->createNotFoundException();
        }

        return new Response(
            (string) file_get_contents($path),
            Response::HTTP_OK,
            ['Content-Type' => $contentType]
        );
    }
}