<?php

declare(strict_types=1);

require dirname(__DIR__, 2).'/vendor/autoload.php';
require __DIR__.'/ContractFixtures.php';

use App\Article\ArticleDocumentNormalizer;
use App\Article\ArticlePublisher;
use App\Article\SearchTextExtractor;
use App\Article\Validation\ArticleDocumentValidator;
use App\Entity\Post;
use App\Kernel;
use App\Tests\Support\ContractFixtures;
use Doctrine\ORM\EntityManagerInterface;

$kernel = new Kernel('test', false);
$kernel->boot();

try {
    $container = $kernel->getContainer()->get('test.service_container');
    $entityManager = $container->get('doctrine')->getManager();
    assert($entityManager instanceof EntityManagerInterface);
    $entityManager->getConnection()->executeStatement("SET application_name = 'article-publisher-race'");

    $post = $entityManager->find(Post::class, (int) $argv[1]);
    if (!$post instanceof Post) {
        throw new RuntimeException('The test post is missing.');
    }

    $sanitizer = ContractFixtures::sanitizer();
    $publisher = new ArticlePublisher(
        $entityManager,
        new ArticleDocumentNormalizer($sanitizer),
        new ArticleDocumentValidator(ContractFixtures::urlPolicy(), $sanitizer),
        new SearchTextExtractor(),
    );
    $publisher->unpublish($post, 'en', 2);
} catch (Throwable $e) {
    fwrite(STDERR, get_class($e).': '.$e->getMessage()."\n");
    exit(1);
} finally {
    $kernel->shutdown();
}
