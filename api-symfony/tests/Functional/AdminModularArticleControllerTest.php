<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Post;
use App\Entity\PostLocale;
use App\Tests\Support\ContractFixtures;
use Symfony\Component\HttpFoundation\Request;

final class AdminModularArticleControllerTest extends DatabaseWebTestCase
{
    public function testAnonymousVisitorIsSentToLogin(): void
    {
        $this->client->request(Request::METHOD_GET, '/admin/articles/');

        $this->assertResponseRedirects();
    }

    public function testAdminPagesAreReachable(): void
    {
        $this->client->loginUser($this->createAdminUser());

        $this->client->request(Request::METHOD_GET, '/admin/articles/');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Modular articles');

        $this->client->request(Request::METHOD_GET, '/admin/articles/new');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form[action="/admin/articles/new"] input[name="_token"]');
    }

    public function testCreationRequiresCsrfAndOpensTheEditor(): void
    {
        $this->client->loginUser($this->createAdminUser());
        $form = ['slug' => 'cards', 'title' => 'Cards', 'primary_locale' => 'en'];

        $this->client->request(Request::METHOD_POST, '/admin/articles/new', $form + ['_token' => 'wrong']);
        $this->assertResponseStatusCodeSame(403);
        $this->assertNull($this->em->getRepository(Post::class)->findOneBy(['slug' => 'cards']));

        $id = $this->createArticle('cards');

        $anchor = $this->em->find(Post::class, $id);
        $this->assertInstanceOf(Post::class, $anchor);
        $this->assertTrue($anchor->isModular());
        $this->assertSame('en', $anchor->getPrimaryLocale());
    }

    public function testDuplicateAndInvalidSlugsShowErrors(): void
    {
        $this->client->loginUser($this->createAdminUser());
        $this->createArticle('cards');

        $token = $this->formToken('/admin/articles/new');
        $this->client->request(Request::METHOD_POST, '/admin/articles/new', ['slug' => 'cards', 'title' => 'x', 'primary_locale' => 'en', '_token' => $token]);
        $this->assertResponseStatusCodeSame(422);
        $this->assertSelectorTextContains('.alert-danger', 'already used');

        $this->client->request(Request::METHOD_POST, '/admin/articles/new', ['slug' => 'Bad Slug', 'title' => 'x', 'primary_locale' => 'en', '_token' => $token]);
        $this->assertResponseStatusCodeSame(422);
    }

    public function testEditorPageEmbedsTheDocumentWithoutBreakingOutOfTheScriptTag(): void
    {
        $this->client->loginUser($this->createAdminUser());
        $id = $this->createArticle('cards');
        $document = ContractFixtures::validDocument('cards-en');
        $document['blocks'][0]['props'] = ['html' => '<p>x</p>'];
        $csrf = $this->editorToken($id, 'en');

        $this->saveJson($id, 'en', $csrf, ['title' => '</script><script>alert(1)</script>', 'summary' => 's', 'document' => $document]);
        $this->assertResponseIsSuccessful();

        $this->client->request(Request::METHOD_GET, \sprintf('/admin/articles/%d/en', $id));
        $this->assertResponseIsSuccessful();
        $content = (string) $this->client->getResponse()->getContent();
        $this->assertStringNotContainsString('</script><script>alert(1)', $content);
        $this->assertSelectorExists('script#article-initial');
    }

    public function testSavingNeedsCsrfAndStoresSanitizedDraft(): void
    {
        $this->client->loginUser($this->createAdminUser());
        $id = $this->createArticle('cards');
        $csrf = $this->editorToken($id, 'en');
        $document = ContractFixtures::validDocument('cards-en');
        $document['blocks'][0] = [
            'id' => $document['blocks'][0]['id'],
            'type' => 'richText',
            'props' => ['html' => '<p>ok</p><script>alert(1)</script><img src=x onerror=alert(1)>'],
        ];
        $body = ['title' => 'Cards', 'summary' => 'Summary', 'document' => $document];

        $this->saveJson($id, 'en', 'wrong', $body);
        $this->assertResponseStatusCodeSame(403);

        $this->saveJson($id, 'en', $csrf, $body);
        $this->assertResponseIsSuccessful();
        $this->assertTrue($this->json()['ok']);

        $this->em->clear();
        $row = $this->em->find(PostLocale::class, ['postId' => $id, 'locale' => 'en']);
        $this->assertInstanceOf(PostLocale::class, $row);
        $this->assertFalse($row->isPublished());
        $stored = $row->getDocument()['blocks'][0]['props']['html'];
        $this->assertStringContainsString('<p>ok</p>', $stored);
        $this->assertStringNotContainsString('script', $stored);
        $this->assertStringNotContainsString('onerror', $stored);
    }

    public function testInvalidDocumentReturnsIssues(): void
    {
        $this->client->loginUser($this->createAdminUser());
        $id = $this->createArticle('cards');
        $csrf = $this->editorToken($id, 'en');
        $document = ['schema_version' => 1, 'locale' => 'en', 'blocks' => [['id' => 'x1', 'type' => 'nope', 'props' => []]]];

        $this->saveJson($id, 'en', $csrf, ['title' => 'Cards', 'summary' => 's', 'document' => $document]);

        $this->assertResponseStatusCodeSame(422);
        $payload = $this->json();
        $this->assertFalse($payload['ok']);
        $this->assertNotEmpty($payload['issues']);
        $this->assertSame('unknown_block_type', $payload['issues'][0]['code']);

        $this->client->request(Request::METHOD_POST, \sprintf('/admin/articles/%d/en/save', $id), [], [], ['HTTP_X_CSRF_TOKEN' => $csrf], '{not json');
        $this->assertResponseStatusCodeSame(400);
    }

    public function testPublishAndUnpublishNeedCsrfAndChangeVisibility(): void
    {
        $this->client->loginUser($this->createAdminUser());
        $id = $this->createArticle('cards');
        $csrf = $this->editorToken($id, 'en');
        $this->saveJson($id, 'en', $csrf, [
            'title' => 'Cards', 'summary' => 'Summary', 'meta_description' => 'Description',
            'document' => ContractFixtures::validDocument('cards-en'),
        ]);
        $this->assertResponseIsSuccessful();

        $this->client->request(Request::METHOD_POST, \sprintf('/admin/articles/%d/en/publish', $id), ['_token' => 'wrong']);
        $this->assertResponseStatusCodeSame(403);

        $this->client->request(Request::METHOD_POST, \sprintf('/admin/articles/%d/en/publish', $id), ['_token' => $csrf]);
        $this->assertResponseRedirects(\sprintf('/admin/articles/%d/en', $id));
        $this->em->clear();
        $this->assertTrue($this->em->find(PostLocale::class, ['postId' => $id, 'locale' => 'en'])?->isPublished());
        $this->assertFalse($this->em->find(Post::class, $id)?->getStatus());

        $this->client->request(Request::METHOD_POST, \sprintf('/admin/articles/%d/en/unpublish', $id), ['_token' => $csrf]);
        $this->assertResponseRedirects(\sprintf('/admin/articles/%d/en', $id));
        $this->em->clear();
        $this->assertFalse($this->em->find(PostLocale::class, ['postId' => $id, 'locale' => 'en'])?->isPublished());
        $this->assertTrue($this->em->find(Post::class, $id)?->getStatus());
    }

    public function testPublishingAnIncompleteDraftIsRefusedWithAMessage(): void
    {
        $this->client->loginUser($this->createAdminUser());
        $id = $this->createArticle('cards');
        $csrf = $this->editorToken($id, 'en');
        $this->saveJson($id, 'en', $csrf, [
            'title' => 'Cards', 'summary' => '',
            'document' => ['schema_version' => 1, 'locale' => 'en', 'blocks' => []],
        ]);
        $this->assertResponseIsSuccessful();

        $this->client->request(Request::METHOD_POST, \sprintf('/admin/articles/%d/en/publish', $id), ['_token' => $csrf]);
        $this->assertResponseRedirects();
        $this->client->followRedirect();
        $this->assertSelectorExists('.alert-danger');
        $this->em->clear();
        $this->assertFalse($this->em->find(PostLocale::class, ['postId' => $id, 'locale' => 'en'])?->isPublished());
    }

    public function testLegacyPostIsNotEditableHere(): void
    {
        $admin = $this->createAdminUser();
        $post = new Post();
        $post->setTitle('Old')->setSlug('old')->setContent('c')->setDescription('d')->setIsFeatured(false)->setAuthor($admin);
        $this->em->persist($post);
        $this->em->flush();

        $this->client->loginUser($admin);
        $this->client->request(Request::METHOD_GET, \sprintf('/admin/articles/%d/en', (int) $post->getId()));

        $this->assertResponseStatusCodeSame(404);
    }

    private function createArticle(string $slug): int
    {
        $token = $this->formToken('/admin/articles/new');
        $this->client->request(Request::METHOD_POST, '/admin/articles/new', ['slug' => $slug, 'title' => ucfirst($slug), 'primary_locale' => 'en', '_token' => $token]);
        $this->assertResponseRedirects();
        $anchor = $this->em->getRepository(Post::class)->findOneBy(['slug' => $slug]);
        $this->assertInstanceOf(Post::class, $anchor);
        $this->assertStringEndsWith(\sprintf('/admin/articles/%d/en', (int) $anchor->getId()), (string) $this->client->getResponse()->headers->get('Location'));

        return (int) $anchor->getId();
    }

    private function formToken(string $path): string
    {
        $crawler = $this->client->request(Request::METHOD_GET, $path);

        return (string) $crawler->filter('input[name="_token"]')->attr('value');
    }

    private function editorToken(int $id, string $locale): string
    {
        $crawler = $this->client->request(Request::METHOD_GET, \sprintf('/admin/articles/%d/%s', $id, $locale));
        $this->assertResponseIsSuccessful();
        $data = json_decode($crawler->filter('script#article-initial')->text(null, false), true, 512, \JSON_THROW_ON_ERROR);

        return (string) $data['csrf'];
    }

    /**
     * @param array<string, mixed> $body
     */
    private function saveJson(int $id, string $locale, string $csrf, array $body): void
    {
        $this->client->request(
            Request::METHOD_POST,
            \sprintf('/admin/articles/%d/%s/save', $id, $locale),
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_X_CSRF_TOKEN' => $csrf],
            json_encode($body, \JSON_THROW_ON_ERROR),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function json(): array
    {
        return json_decode((string) $this->client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
    }
}
