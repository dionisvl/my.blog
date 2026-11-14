<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Category;
use App\Entity\Product;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Request;

final class AdminProductControllerTest extends DatabaseWebTestCase
{
    public static function provideProductPayloads(): iterable
    {
        yield 'basic product' => ['Coffee Beans', 'Strong beans', 1990, 1000];

        yield 'simple product' => ['Tea', 'Green tea', 1200, 50];
    }

    #[DataProvider('provideProductPayloads')]
    public function testCreateUpdateDeleteProduct(string $title, string $detailText, int $price, int $balance): void
    {
        $user = $this->createAdminUser();
        $category = new Category();
        $category->setTitle('Shop Category');
        $category->setSlug('shop-category');

        $this->em->persist($category);
        $this->em->flush();

        $this->client->loginUser($user);

        $this->client->request(Request::METHOD_POST, '/admin/products/store', [
            'title' => $title,
            'detail_text' => $detailText,
            'price' => $price,
            'balance' => $balance,
            'category_id' => $category->getId(),
            'date' => '2025-01-01',
            'features' => 'Features',
            'delivery' => 'Delivery',
            'composition' => 'Composition',
            'size' => '100g',
            'manufacturer' => 'ACME',
            'stars' => 100,
        ]);

        $this->assertResponseRedirects('/admin/products/');

        $this->em->clear();
        $product = $this->em->getRepository(Product::class)->findOneBy(['title' => $title]);
        self::assertNotNull($product);
        self::assertSame($price, $product->getPrice());

        $newTitle = $title.' Updated';
        $this->client->request(Request::METHOD_POST, '/admin/products/'.$product->getId().'/update', [
            'title' => $newTitle,
            'detail_text' => $detailText,
            'price' => $price,
            'balance' => $balance,
            'category_id' => $category->getId(),
        ]);

        $this->assertResponseRedirects('/admin/products/');

        $this->em->clear();
        $updated = $this->em->getRepository(Product::class)->find($product->getId());
        self::assertNotNull($updated);
        self::assertSame($newTitle, $updated->getTitle());

        $this->client->request(Request::METHOD_POST, '/admin/products/'.$product->getId().'/delete');
        $this->assertResponseRedirects('/admin/products/');

        $this->em->clear();
        $deleted = $this->em->getRepository(Product::class)->find($product->getId());
        self::assertNull($deleted);
    }

    public function testProductIndexView(): void
    {
        $user = $this->createAdminUser();
        $product = new Product();
        $product->setTitle('Index Product');
        $product->setSlug('index-product');

        $this->em->persist($product);
        $this->em->flush();

        $this->client->loginUser($user);
        $this->client->request(Request::METHOD_GET, '/admin/products/');

        $this->assertResponseIsSuccessful();
        self::assertStringContainsString('Index Product', $this->client->getResponse()->getContent());
    }

    public function testProductCreateView(): void
    {
        $user = $this->createAdminUser();
        $this->client->loginUser($user);
        $this->client->request(Request::METHOD_GET, '/admin/products/create');

        $this->assertResponseIsSuccessful();
        $action = self::getContainer()->get('router')->generate('admin_products_store');
        $this->assertSelectorExists(\sprintf('form[action="%s"]', $action));
    }

    public function testProductEditView(): void
    {
        $user = $this->createAdminUser();
        $product = new Product();
        $product->setTitle('Edit Product');
        $product->setSlug('edit-product');

        $this->em->persist($product);
        $this->em->flush();

        $this->client->loginUser($user);
        $this->client->request(Request::METHOD_GET, '/admin/products/'.$product->getId().'/edit');

        $this->assertResponseIsSuccessful();
        $action = self::getContainer()->get('router')->generate('admin_products_update', ['id' => $product->getId()]);
        $this->assertSelectorExists(\sprintf('form[action="%s"]', $action));
    }

    public function testProductValidationErrorsReturnJson(): void
    {
        $user = $this->createAdminUser();
        $this->client->loginUser($user);

        $this->client->request(Request::METHOD_POST, '/admin/products/store', [
            'title' => '',
            'detail_text' => '',
        ]);

        $this->assertResponseStatusCodeSame(422);
        self::assertJson($this->client->getResponse()->getContent());
        self::assertStringContainsString('title', $this->client->getResponse()->getContent());
    }

    public function testCreateProductWithImageUploads(): void
    {
        $user = $this->createAdminUser();
        $category = new Category();
        $category->setTitle('Shop Category');
        $category->setSlug('shop-category');

        $this->em->persist($category);
        $this->em->flush();

        $this->client->loginUser($user);

        $tmpPreview = tempnam(sys_get_temp_dir(), 'upl').'.png';
        file_put_contents($tmpPreview, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAQAAAAECAIAAAAmkwkpAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAADElEQVQImWNgIB0AAAA0AAEjQ4N1AAAAAElFTkSuQmCC'));
        $previewFile = new \Symfony\Component\HttpFoundation\File\UploadedFile($tmpPreview, 'preview.png', 'image/png', null, true);

        $tmpDetail = tempnam(sys_get_temp_dir(), 'upl').'.png';
        file_put_contents($tmpDetail, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAQAAAAECAIAAAAmkwkpAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAADElEQVQImWNgIB0AAAA0AAEjQ4N1AAAAAElFTkSuQmCC'));
        $detailFile = new \Symfony\Component\HttpFoundation\File\UploadedFile($tmpDetail, 'detail.png', 'image/png', null, true);

        try {
            $this->client->request(Request::METHOD_POST, '/admin/products/store', [
                'title' => 'Product with Images',
                'detail_text' => 'Product detail text',
                'price' => 2000,
                'balance' => 500,
                'category_id' => $category->getId(),
                'date' => '2025-06-01',
                'features' => 'Product features',
                'delivery' => 'Fast delivery',
                'composition' => 'Product composition',
                'size' => '500g',
                'manufacturer' => 'Test Manufacturer',
                'stars' => 95,
            ], [
                'preview_picture' => $previewFile,
                'detail_picture' => $detailFile,
            ]);

            $this->assertResponseRedirects('/admin/products/');

            $this->em->clear();
            $product = $this->em->getRepository(Product::class)->findOneBy(['title' => 'Product with Images']);
            self::assertNotNull($product);
            self::assertNotNull($product->getPreviewPicture());
            self::assertNotNull($product->getDetailPicture());

            $projectDir = self::getContainer()->getParameter('kernel.project_dir');
            $previewPicture = $this->em->getRepository(Product::class)->find($product->getId())->getPreviewPicture();
            $detailPicture = $this->em->getRepository(Product::class)->find($product->getId())->getDetailPicture();
            $previewPath = $projectDir.'/public/storage/shop_uploads/'.$previewPicture;
            $detailPath = $projectDir.'/public/storage/shop_uploads/'.$detailPicture;
            self::assertFileExists($previewPath);
            self::assertFileExists($detailPath);

            $this->client->request(Request::METHOD_POST, '/admin/products/'.$product->getId().'/delete');
            $this->assertResponseRedirects('/admin/products/');

            $this->em->clear();
            $deleted = $this->em->getRepository(Product::class)->find($product->getId());
            self::assertNull($deleted);
        } finally {
            if (file_exists($tmpPreview)) {
                unlink($tmpPreview);
            }
            if (file_exists($tmpDetail)) {
                unlink($tmpDetail);
            }
        }
    }
}
