<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Post;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class PostRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Post::class);
    }

    /**
     * @return list<Post>
     */
    public function findAllOrderedByCreatedAtDesc(): array
    {
        /** @var list<Post> $rows */
        $rows = $this->createQueryBuilder('p')
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $rows;
    }

    /**
     * @return list<Post>
     */
    public function findLatest(int $limit = 5): array
    {
        /** @var list<Post> $rows */
        $rows = $this->createQueryBuilder('p')
            ->orderBy('p.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $rows;
    }

    /**
     * @return list<Post>
     */
    public function findPublishedLatest(int $limit = 20): array
    {
        /** @var list<Post> $rows */
        $rows = $this->createQueryBuilder('p')
            ->where('p.status = :status')
            ->setParameter('status', false)
            ->orderBy('p.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $rows;
    }

    /**
     * @return array{items: list<Post>, total: int}
     */
    public function findPublishedLatestPaginated(int $page, int $perPage): array
    {
        $query = $this->createQueryBuilder('p')
            ->where('p.status = :status')
            ->setParameter('status', false)
            ->orderBy('p.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery();

        $paginator = new \Doctrine\ORM\Tools\Pagination\Paginator($query);

        return [
            'items' => iterator_to_array($paginator),
            'total' => $paginator->count(),
        ];
    }

    /**
     * @return array{items: list<Post>, total: int}
     */
    public function findLatestPaginated(int $page, int $perPage): array
    {
        $query = $this->createQueryBuilder('p')
            ->orderBy('p.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery();

        $paginator = new \Doctrine\ORM\Tools\Pagination\Paginator($query);

        return [
            'items' => iterator_to_array($paginator),
            'total' => $paginator->count(),
        ];
    }

    /**
     * @return list<Post>
     */
    public function findFeatured(int $limit = 3): array
    {
        /** @var list<Post> $rows */
        $rows = $this->createQueryBuilder('p')
            ->where('p.isFeatured = :featured')
            ->andWhere('p.status = :status')
            ->setParameter('featured', true)
            ->setParameter('status', false)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $rows;
    }

    /**
     * @return list<Post>
     */
    public function findRecentPublished(int $limit = 4): array
    {
        /** @var list<Post> $rows */
        $rows = $this->createQueryBuilder('p')
            ->where('p.status = :status')
            ->setParameter('status', false)
            ->orderBy('p.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $rows;
    }

    public function findPublishedById(int $id): ?Post
    {
        /** @var Post|null $post */
        $post = $this->createQueryBuilder('p')
            ->where('p.id = :id')
            ->andWhere('p.status = :status')
            ->setParameter('id', $id)
            ->setParameter('status', false)
            ->getQuery()
            ->getOneOrNullResult();

        return $post;
    }

    public function findRandomPublishedId(): ?int
    {
        $count = (int)$this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.status = :status')
            ->setParameter('status', false)
            ->getQuery()
            ->getSingleScalarResult();

        if ($count === 0) {
            return null;
        }

        $offset = random_int(0, $count - 1);

        $id = $this->createQueryBuilder('p')
            ->select('p.id')
            ->where('p.status = :status')
            ->setParameter('status', false)
            ->orderBy('p.id', 'ASC')
            ->setFirstResult($offset)
            ->setMaxResults(1)
            ->getQuery()
            ->getSingleScalarResult();

        return (int)$id;
    }

    public function findPublishedBySlug(string $slug): ?Post
    {
        /** @var Post|null $post */
        $post = $this->createQueryBuilder('p')
            ->where('p.slug = :slug')
            ->andWhere('p.status = :status')
            ->setParameter('slug', $slug)
            ->setParameter('status', false)
            ->getQuery()
            ->getOneOrNullResult();

        return $post;
    }

    /**
     * @return list<Post>
     */
    public function findPublishedByCategorySlug(string $slug): array
    {
        /** @var list<Post> $rows */
        $rows = $this->createQueryBuilder('p')
            ->innerJoin('p.category', 'c')
            ->where('c.slug = :slug')
            ->andWhere('p.status = :status')
            ->setParameter('slug', $slug)
            ->setParameter('status', false)
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $rows;
    }

    /**
     * @return array{items: list<Post>, total: int}
     */
    public function findPublishedByCategorySlugPaginated(string $slug, int $page, int $perPage): array
    {
        $query = $this->createQueryBuilder('p')
            ->innerJoin('p.category', 'c')
            ->where('c.slug = :slug')
            ->andWhere('p.status = :status')
            ->setParameter('slug', $slug)
            ->setParameter('status', false)
            ->orderBy('p.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery();

        $paginator = new \Doctrine\ORM\Tools\Pagination\Paginator($query);

        return [
            'items' => iterator_to_array($paginator),
            'total' => $paginator->count(),
        ];
    }

    /**
     * @return list<Post>
     */
    public function findPublishedByTagSlug(string $slug): array
    {
        /** @var list<Post> $rows */
        $rows = $this->createQueryBuilder('p')
            ->innerJoin('p.tags', 't')
            ->where('t.slug = :slug')
            ->andWhere('p.status = :status')
            ->setParameter('slug', $slug)
            ->setParameter('status', false)
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $rows;
    }

    /**
     * @return list<Post>
     */
    public function searchPosts(string $query, int $limit, bool $includeDrafts = false): array
    {
        $builder = $this->createQueryBuilder('p')
            ->where('p.title LIKE :query')
            ->orWhere('p.description LIKE :query')
            ->orWhere('p.content LIKE :query')
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('p.createdAt', 'DESC')
            ->setMaxResults($limit);

        if (!$includeDrafts) {
            $builder->andWhere('p.status = :status')
                ->setParameter('status', false);
        }

        /** @var list<Post> $rows */
        $rows = $builder->getQuery()->getResult();

        return $rows;
    }

    /**
     * @return array{items: list<Post>, total: int}
     */
    public function findPublishedByTagSlugPaginated(string $slug, int $page, int $perPage): array
    {
        $query = $this->createQueryBuilder('p')
            ->select('DISTINCT p')
            ->innerJoin('p.tags', 't')
            ->where('t.slug = :slug')
            ->andWhere('p.status = :status')
            ->setParameter('slug', $slug)
            ->setParameter('status', false)
            ->orderBy('p.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery();

        $paginator = new \Doctrine\ORM\Tools\Pagination\Paginator($query, true);

        return [
            'items' => iterator_to_array($paginator),
            'total' => $paginator->count(),
        ];
    }
}
